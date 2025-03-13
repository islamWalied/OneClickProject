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
        $name = $this->validateName($this->argument('name'));
        if (!$name) {
            $this->error("Invalid project name. Aborting.");
            return 1;
        }

        // Check and perform setup if needed
        if (!$this->isSetupComplete()) {
            $this->ensureApiRoutesFileExists();
            $this->updateAppConfig();
        }

        $modelGenerator = new ModelGenerator($this);
        if (!$modelGenerator->generate($name, $this->attributes)) {
            return 1;
        }

        $this->askForCustomMethods();

        (new MigrationGenerator($this))->generate($name, $this->attributes);
        (new RepositoryGenerator($this))->generate($name, $this->customMethods);
        (new ServiceGenerator($this))->generate($name, $this->customMethods, $this->attributes);
        (new ResourceGenerator($this))->generate($name, $this->attributes);
        (new ControllerGenerator($this))->generate($name);
        (new RequestGenerator($this))->generate($name, $this->attributes);
        (new RouteGenerator($this))->generate($name);

        $this->info("Entity '{$name}' generated successfully!");
        if (!$this->isSetupComplete()) {
            $this->info("Note: You may need to restart your application (e.g., 'sail down && sail up -d' if using Laravel Sail) to apply changes to bootstrap/app.php.");
        }
        return 0;
    }

    protected function validateName(string $name): ?string
    {
        if (!preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error("Name '{$name}' is invalid. Use alphanumeric characters starting with a letter.");
            return null;
        }
        return $name;
    }

    protected function isSetupComplete(): bool
    {
        $appPath = base_path('bootstrap/app.php');

        if (!File::exists($appPath)) {
            $this->warn("bootstrap/app.php not found. Setup will be performed.");
            return false;
        }

        $content = File::get($appPath);
        $requiredPatterns = [
            'api: __DIR__.\'/../routes/api.php\'' => 'API routing',
            "'cors' => App\\Http\\Middleware\\Cors::class" => 'CORS middleware alias',
            "'throttle' => App\\Http\\Middleware\\ThrottleRequests::class" => 'Throttle middleware alias',
        ];

        foreach ($requiredPatterns as $pattern => $description) {
            if (strpos($content, $pattern) === false) {
                $this->warn("{$description} not configured in bootstrap/app.php. Setup will be performed.");
                return false;
            }
        }

        return true;
    }

    protected function ensureApiRoutesFileExists()
    {
        $apiRoutePath = base_path('routes/api.php');

        if (File::exists($apiRoutePath)) {
            $this->info("API routes file already exists at '{$apiRoutePath}'.");
            return;
        }

        $apiRouteContent = <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application.
|
*/
PHP;

        File::put($apiRoutePath, $apiRouteContent);
        $this->info("Created routes/api.php at '{$apiRoutePath}'.");
    }

    protected function updateAppConfig()
    {
        $appPath = base_path('bootstrap/app.php');

        if (!File::exists($appPath)) {
            $this->error("bootstrap/app.php not found. Please ensure your Laravel installation is complete.");
            return;
        }

        $content = File::get($appPath);
        $backupPath = base_path('bootstrap/app.php.backup_' . time());
        File::copy($appPath, $backupPath);
        $this->info("Backed up bootstrap/app.php to '{$backupPath}'.");

        $useStatements = [
            'use Illuminate\Foundation\Application;',
            'use Illuminate\Foundation\Configuration\Exceptions;',
            'use Illuminate\Foundation\Configuration\Middleware;',
            'use Illuminate\Http\Request;'
        ];

        $newUseStatements = array_filter($useStatements, fn($stmt) => strpos($content, $stmt) === false);
        if (!empty($newUseStatements)) {
            $content = preg_replace('/<\?php/', "<?php\n\n" . implode("\n", $newUseStatements) . "\n", $content);
            $this->info("Added missing use statements to bootstrap/app.php.");
        }

        $routingBlock = <<<'PHP'
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
PHP;

        $middlewareBlock = <<<'PHP'
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'lang' => App\Http\Middleware\Lang::class,
            'cors' => App\Http\Middleware\Cors::class,
            'throttle' => App\Http\Middleware\ThrottleRequests::class,
        ]);
    })
PHP;

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

        if (strpos($content, '->withRouting') === false) {
            $content = str_replace(
                'return Application::configure(basePath: dirname(__DIR__))',
                "return Application::configure(basePath: dirname(__DIR__))\n{$routingBlock}",
                $content
            );
        } else {
            $content = preg_replace(
                '/->withRouting\([^\)]+\)/',
                $routingBlock,
                $content
            );
        }

        if (strpos($content, '->withMiddleware') === false) {
            $content = str_replace(
                $routingBlock,
                "{$routingBlock}\n{$middlewareBlock}",
                $content
            );
        } else {
            $content = preg_replace(
                '/->withMiddleware\(function \(Middleware \$middleware\) \{[^\}]+\}\)/',
                $middlewareBlock,
                $content
            );
        }

        if (strpos($content, '->withExceptions') === false) {
            $content = str_replace(
                $middlewareBlock,
                "{$middlewareBlock}\n{$exceptionsBlock}",
                $content
            );
        } else {
            $content = preg_replace(
                '/->withExceptions\(function \(Exceptions \$exceptions\) \{[^\}]+\}\)/',
                $exceptionsBlock,
                $content
            );
        }

        File::put($appPath, $content);
        $this->info("Updated bootstrap/app.php with API routing, middleware aliases, and exception handling.");
    }

    protected function askForCustomMethods()
    {
        while (true) {
            $response = strtolower($this->ask('Do you want to add a custom method in the repository? (yes/no)', 'no'));
            if ($response === 'no') {
                break;
            } elseif ($response !== 'yes') {
                $this->error("Invalid input '{$response}'. Please enter 'yes' or 'no'.");
                continue;
            }

            $methodName = $this->promptForValidName('Enter method name (e.g., findByEmail):');
            if (!$methodName) continue;

            $returnType = $this->promptForValidReturnType('Enter return type (e.g., mixed, string, int):', 'mixed');
            if (!$returnType) continue;

            $params = $this->promptForValidParams('Enter parameters (e.g., "string $email, int $id") or leave empty:');
            if ($params === false) continue;

            $implementInService = $this->confirm('Do you want to implement this method in the service?', true);

            $this->customMethods[] = [
                'name' => $methodName,
                'returnType' => $returnType,
                'params' => $params,
                'implementInService' => $implementInService,
            ];
            $this->info("Added custom method '{$methodName}' to the list.");
        }
    }

    protected function promptForValidName(string $prompt): ?string
    {
        while (true) {
            $name = $this->ask($prompt);
            if (empty($name)) {
                $this->error("Name cannot be empty.");
                continue;
            }
            if (preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name)) {
                return $name;
            }
            $this->error("Invalid name '{$name}'. Use alphanumeric characters starting with a letter.");
        }
    }

    protected function promptForValidReturnType(string $prompt, string $default = 'mixed'): ?string
    {
        $validTypes = ['mixed', 'void', 'bool', 'int', 'string', 'array', 'Model', 'Collection'];
        while (true) {
            $type = $this->ask($prompt, $default);
            if (in_array($type, $validTypes)) {
                return $type;
            }
            $this->error("Invalid return type '{$type}'. Valid options: " . implode(', ', $validTypes));
        }
    }

    protected function promptForValidParams(string $prompt): string|bool
    {
        while (true) {
            $params = trim($this->ask($prompt, ''));
            if ($params === '') {
                return '';
            }
            if (preg_match('/^([a-zA-Z]+\s+\$[a-zA-Z0-9]+(,\s*)?)+$/', $params)) {
                return $params;
            }
            $this->error("Invalid parameters format '{$params}'. Use 'type $name' syntax (e.g., 'string $email, int $id').");
            if ($this->confirm('Skip parameter input and leave empty?', false)) {
                return '';
            }
        }
    }
}