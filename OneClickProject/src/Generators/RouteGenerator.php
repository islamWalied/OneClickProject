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
        $this->createApiFolderStructure();
        $this->generateApiRoutes($name);
        $this->updateApiRoutesFile();
    }

    protected function createApiFolderStructure()
    {
        $apiControllerPath = app_path('Http/Controllers/Api');
        $apiRoutesPath = base_path('routes/api');

        if (!File::exists($apiControllerPath)) {
            File::makeDirectory($apiControllerPath, 0755, true);
        }

        if (!File::exists($apiRoutesPath)) {
            File::makeDirectory($apiRoutesPath, 0755, true);
        }
    }

    protected function generateApiRoutes(string $name)
    {
        $lower = strtolower($name);
        $routesPath = base_path("routes/api/{$lower}.php");

        if (File::exists($routesPath)) {
            $this->command->error("API Routes file for {$name} already exists!");
            return;
        }

        $routesContent = $this->getRoutesTemplate($name);
        File::put($routesPath, $routesContent);

        $this->command->info("API Routes file for {$name} created successfully!");
    }

    protected function getRoutesTemplate(string $name): string
    {
        $pluralName = Str::plural(strtolower($name));
        $snakeCaseLower = Str::snake($name);
        return <<<EOF
<?php
use App\Http\Controllers\\{$name}Controller;
use Illuminate\Support\Facades\Route;

Route::middleware(['cors','lang','throttle'])->prefix('v1/')->group(function () {
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('{$pluralName}', [{$name}Controller::class, 'index']);
        Route::get('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'show']);
        Route::post('{$pluralName}', [{$name}Controller::class, 'store']);
        Route::patch('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'update']);
        Route::delete('{$pluralName}/{{$snakeCaseLower}}', [{$name}Controller::class, 'destroy']);
    });
});
EOF;
    }

    protected function updateApiRoutesFile()
    {
        $apiRoutesPath = base_path('routes/api.php');

        if (!File::exists($apiRoutesPath)) {
            $this->command->error('The api.php file does not exist in the routes directory!');
            return;
        }

        $content = File::get($apiRoutesPath);
        if (str_contains($content, 'RouteHelper::includeRouteFiles')) {
            $this->command->info('The api.php file already includes the RouteHelper line.');
            return;
        }

        $routeHelperLine = "<?php\n\nuse App\Helpers\Routes\\v1\RouteHelper;\n\nRouteHelper::includeRouteFiles(__DIR__ . '/api/');\n";
        File::append($apiRoutesPath, $routeHelperLine);

        $this->command->info('The api.php file has been updated to include RouteHelper.');
    }
}