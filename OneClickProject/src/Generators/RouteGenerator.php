<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RouteGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid route name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        $methods = [
            'index' => 'GET list all resources',
            'show' => 'GET show a single resource',
            'store' => 'POST create a resource',
            'update' => 'PATCH update a resource',
            'destroy' => 'DELETE delete a resource',
        ];

        $this->command->info("Specify which methods should require authentication (auth:sanctum):");
        $options = array_map(fn($method, $desc) => "$method - $desc", array_keys($methods), $methods);
        $authenticatedMethods = $this->promptForAuthenticatedMethods($options, array_keys($methods));

        $this->createApiFolderStructure();
        $this->generateApiRoutes($name, $authenticatedMethods);
        $this->updateApiRoutesFile();
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    protected function promptForAuthenticatedMethods(array $options, array $allMethods): array
    {
        $authenticatedMethods = $this->command->choice(
            "Select methods to be authenticated (comma-separated numbers, press Enter for all, e.g., '0, 2'):",
            $options,
            null,
            null,
            true
        );

        if (empty($authenticatedMethods)) {
            $this->command->info("No selection made. Defaulting to all methods: " . implode(', ', $allMethods));
            return $allMethods;
        }

        $selectedMethods = array_map(fn($choice) => explode(' - ', $choice)[0], $authenticatedMethods);
        $this->command->info("Selected authenticated methods: " . implode(', ', $selectedMethods));
        return $selectedMethods;
    }

    protected function createApiFolderStructure()
    {
        $paths = [
            'controllers' => app_path('Http/Controllers/Api'),
            'routes' => base_path('routes/api')
        ];

        foreach ($paths as $type => $path) {
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
                $this->command->info("Created '{$type}' directory at '{$path}'.");
            }
        }
    }

    protected function generateApiRoutes(string $name, array $authenticatedMethods)
    {
        $lower = strtolower($name);
        $routesPath = base_path("routes/api/{$lower}.php");

        if (File::exists($routesPath)) {
            $this->command->error("API routes file for '{$name}' already exists at '{$routesPath}'!");
            return;
        }

        $routesContent = $this->getRoutesTemplate($name, $authenticatedMethods);
        File::put($routesPath, $routesContent);
        $this->command->info("API routes file for '{$name}' created successfully!");
    }

    protected function getRoutesTemplate(string $name, array $authenticatedMethods): string
    {
        $pluralName = Str::plural(strtolower($name));
        $snakeCaseLower = Str::snake($name);

        $allRoutes = [
            'index' => "Route::get('{$pluralName}', [{$name}Controller::class, 'index']);",
            'show' => "Route::get('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'show']);",
            'store' => "Route::post('{$pluralName}', [{$name}Controller::class, 'store']);",
            'update' => "Route::patch('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'update']);",
            'destroy' => "Route::delete('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'destroy']);",
        ];

        $authenticatedRoutes = [];
        $unauthenticatedRoutes = [];
        foreach ($allRoutes as $method => $route) {
            if (in_array($method, $authenticatedMethods)) {
                $authenticatedRoutes[] = "        $route";
            } else {
                $unauthenticatedRoutes[] = "    $route";
            }
        }

        $authenticatedSection = !empty($authenticatedRoutes) ?
            "    Route::middleware(['auth:sanctum'])->group(function () {\n" . implode("\n", $authenticatedRoutes) . "\n    });" : '';
        $unauthenticatedSection = !empty($unauthenticatedRoutes) ? implode("\n", $unauthenticatedRoutes) : '';

        $routesContent = array_filter([$authenticatedSection, $unauthenticatedSection]);
        $routesStr = implode("\n\n", $routesContent);

        return <<<EOF
<?php
use App\Http\Controllers\\{$name}Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['cors', 'lang', 'throttle'])->prefix('v1/')->group(function () {
{$routesStr}
});
EOF;
    }

    protected function updateApiRoutesFile()
    {
        $apiRoutesPath = base_path('routes/api.php');
        if (!File::exists($apiRoutesPath)) {
            $this->command->error("Main routes/api.php file does not exist!");
            return;
        }

        $content = File::get($apiRoutesPath);
        if (str_contains($content, 'RouteHelper::includeRouteFiles')) {
            $this->command->info("routes/api.php already includes RouteHelper.");
            return;
        }

        $routeHelperLine = "<?php\n\nuse App\Helpers\Routes\\v1\RouteHelper;\n\nRouteHelper::includeRouteFiles(__DIR__ . '/api/');\n";
        File::append($apiRoutesPath, $routeHelperLine);
        $this->command->info("Updated routes/api.php with RouteHelper.");
    }
}