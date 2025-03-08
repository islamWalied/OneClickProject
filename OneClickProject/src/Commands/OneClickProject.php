<?php

namespace IslamWalied\OneClickProject\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use IslamWalied\OneClickProject\Generators\ModelGenerator;
use IslamWalied\OneClickProject\Generators\MigrationGenerator;
use IslamWalied\OneClickProject\Generators\RepositoryGenerator;
use IslamWalied\OneClickProject\Generators\ServiceGenerator;
use IslamWalied\OneClickProject\Generators\ResourceGenerator;
use IslamWalied\OneClickProject\Generators\ControllerGenerator;
use IslamWalied\OneClickProject\Generators\RequestGenerator;
use IslamWalied\OneClickProject\Generators\RouteGenerator;

class OneClickProject extends Command
{
    protected $signature = 'generate:project {name}';
    protected $description = 'Create a model, migration, repository, service, resource, controller, requests, and API routes with base repository pattern';

    protected array $attributes = [];
    protected array $customMethods = [];

    public function handle()
    {
        $name = $this->argument('name');

        // Check if setup is needed before proceeding
        if (!$this->isSetupComplete()) {
            $this->ensureApiRoutesFileExists();
            $this->updateAppConfig();
        }

        $modelGenerator = new ModelGenerator($this);
        if (!$modelGenerator->generate($name, $this->attributes)) {
            return;
        }

        $this->askForCustomMethods();

        (new MigrationGenerator($this))->generate($name, $this->attributes);
        (new RepositoryGenerator($this))->generate($name, $this->customMethods);
        (new ServiceGenerator($this))->generate($name, $this->customMethods, $this->attributes);
        (new ResourceGenerator($this))->generate($name, $this->attributes);
        (new ControllerGenerator($this))->generate($name);
        (new RequestGenerator($this))->generate($name, $this->attributes);
        (new RouteGenerator($this))->generate($name);

        $this->info('Project generation completed!');
        if (!$this->isSetupComplete()) {
            $this->info('Note: You may need to restart your application (e.g., if using Laravel Sail, run `sail down && sail up -d`) to apply the changes to bootstrap/app.php.');
        }
    }

    protected function isSetupComplete()
    {
        $appPath = base_path('bootstrap/app.php');

        if (!File::exists($appPath)) {
            return false; // Assume setup is needed if app.php doesn't exist
        }

        $content = File::get($appPath);

        // Check if 'api' route and 'cors' middleware alias are present
        return strpos($content, 'api: __DIR__.\'/../routes/api.php\'') !== false &&
            strpos($content, "'cors' => App\\Http\\Middleware\\Cors::class") !== false;
    }

    protected function ensureApiRoutesFileExists()
    {
        $apiRoutePath = base_path('routes/api.php');

        if (File::exists($apiRoutePath)) {
            return; // Skip if the file already exists
        }
        $apiRouteContent = <<<'PHP'
PHP;

        File::put($apiRoutePath, $apiRouteContent);
        $this->info('Created routes/api.php');
    }

    protected function updateAppConfig()
    {
        $appPath = base_path('bootstrap/app.php');

        if (!File::exists($appPath)) {
            $this->error('bootstrap/app.php not found. Please ensure your Laravel installation is complete.');
            return;
        }

        // Read the existing content
        $content = File::get($appPath);

        // Check if the 'cors' alias is already present to avoid duplicate updates
        if (strpos($content, "'cors' => App\\Http\\Middleware\\Cors::class") !== false) {
            return; // Skip if the configuration is already applied
        }

        // Backup the existing file
        $backupPath = base_path('bootstrap/app.php.backup_' . time());
        File::copy($appPath, $backupPath);
        $this->info("Backed up bootstrap/app.php to $backupPath");

        // Define the use statements to inject
        $useStatements = [
            'use Illuminate\Foundation\Application;',
            'use Illuminate\Foundation\Configuration\Exceptions;',
            'use Illuminate\Foundation\Configuration\Middleware;',
            'use Illuminate\Http\Request;'
        ];

        // Check for each use statement and inject only the missing ones
        $newUseStatements = [];
        foreach ($useStatements as $useStatement) {
            if (strpos($content, $useStatement) === false) {
                $newUseStatements[] = $useStatement;
            }
        }

        if (!empty($newUseStatements)) {
            $content = preg_replace('/<\?php/', "<?php\n\n" . implode("\n", $newUseStatements) . "\n", $content);
        }

        // Define the routing block with api route
        $routingBlock = <<<'PHP'
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
PHP;

        // Define the middleware alias block to inject
        $middlewareBlock = <<<'PHP'
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'lang' => App\Http\Middleware\Lang::class,
            'cors' => App\Http\Middleware\Cors::class, // Added by One Click Project
        ]);
    })
PHP;

        // Define the exceptions block to inject
        $exceptionsBlock = <<<'PHP'
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                $statusCode = method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 422;
                if ($e->getMessage() == "Route [login] not defined.") {
                    return response()->json([
                        'error' => [
                            'message' => $e->getMessage(),
                            'status_code' => 401
                        ]
                    ], 401);
                }
                return response()->json([
                    'error' => [
                        'message' => $e->getMessage(),
                        'status_code' => $statusCode
                    ]
                ], $statusCode);
            }
        });
    })
PHP;

        // Check if routing block exists and update or add it
        if (strpos($content, '->withRouting') === false) {
            $content = str_replace(
                'return Application::configure(basePath: dirname(__DIR__))',
                'return Application::configure(basePath: dirname(__DIR__))\n' . $routingBlock,
                $content
            );
        } else {
            $content = preg_replace(
                '/->withRouting\([^\)]+\)/',
                $routingBlock,
                $content
            );
        }

        // Check if middleware block exists and update or add it
        if (strpos($content, '->withMiddleware') === false) {
            $content = str_replace(
                $routingBlock,
                $routingBlock . "\n" . $middlewareBlock,
                $content
            );
        } else {
            $content = preg_replace(
                '/->withMiddleware\(function \(Middleware \$middleware\) \{[^\}]+\}\)/',
                $middlewareBlock,
                $content
            );
        }

        // Check if exceptions block exists and update or add it
        if (strpos($content, '->withExceptions') === false) {
            $content = str_replace(
                $middlewareBlock,
                $middlewareBlock . "\n" . $exceptionsBlock,
                $content
            );
        } else {
            $content = preg_replace(
                '/->withExceptions\(function \(Exceptions \$exceptions\) \{[^\}]+\}\)/',
                $exceptionsBlock,
                $content
            );
        }

        // Write the updated content
        File::put($appPath, $content);
        $this->info('Updated bootstrap/app.php with API routing, middleware aliases, and exception handling.');
    }

    protected function askForCustomMethods()
    {
        while (true) {
            $response = $this->ask('Do you want to add a custom method in the repository? (yes/no)');

            if (strtolower($response) === 'no') {
                break;
            } elseif (strtolower($response) !== 'yes') {
                $this->error('Invalid input. Please enter "yes" or "no".');
                continue;
            }

            $methodName = $this->ask('Enter method name (e.g., findByEmail)');
            $returnType = $this->ask('Enter return type (default: mixed)', 'mixed');
            $params = $this->ask('Enter parameters (e.g., string $email, int $id)');
            $implementInService = $this->confirm('Do you want to implement this method in the service?', true);

            $this->customMethods[] = [
                'name' => $methodName,
                'returnType' => $returnType,
                'params' => $params,
                'implementInService' => $implementInService,
            ];
        }
    }
}