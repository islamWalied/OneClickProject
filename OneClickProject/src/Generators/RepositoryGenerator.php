<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RepositoryGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array $customMethods)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid repository name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        foreach ($customMethods as $method) {
            if (!isset($method['name']) || !$this->isValidName($method['name'])) {
                $this->command->error("Invalid custom method name '{$method['name']}'. Skipping.");
                return;
            }
        }

        $this->createRepositoryStructure();
        $this->createBaseRepository();
        $this->createRepositoryFiles($name, $customMethods);
        $this->registerRepository($name);
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $name) === 1;
    }

    protected function createRepositoryStructure()
    {
        foreach (['Repositories/Implementation', 'Repositories/Interfaces'] as $dir) {
            $path = app_path($dir);
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    protected function createBaseRepository()
    {
        $baseInterfacePath = app_path('Repositories/Interfaces/BaseRepository.php');
        $baseImplPath = app_path('Repositories/Implementation/BaseRepositoryImpl.php');

        if (File::exists($baseInterfacePath) || File::exists($baseImplPath)) {
            $this->command->warn("Base repository files already exist. Skipping creation.");
            return;
        }

        $baseInterface = <<<EOF
<?php

namespace App\Repositories\Interfaces;

interface BaseRepository
{
    public function index(\$limit);
    public function show(\$model);
    public function findWhere(array \$criteria);
    public function store(array \$data);
    public function update(\$model);
    public function delete(\$model);
}
EOF;

        $baseImplementation = <<<EOF
<?php

namespace App\Repositories\Implementation;

use App\Repositories\Interfaces\BaseRepository;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepositoryImpl implements BaseRepository
{
    protected \$model;

    public function __construct(Model \$model)
    {
        \$this->model = \$model;
    }

    public function index(\$limit)
    {
        return \$this->model->paginate(\$limit);
    }

    public function show(\$model)
    {
        return \$this->model->findOrFail(\$model);
    }

    public function findWhere(array \$criteria)
    {
        return \$this->model->where(\$criteria)->get();
    }

    public function store(array \$data)
    {
        return \$this->model->create(\$data);
    }

    public function update(\$model)
    {
        return \$model->save();
    }

    public function delete(\$model)
    {
        return \$model->delete();
    }
}
EOF;

        File::put($baseInterfacePath, $baseInterface);
        File::put($baseImplPath, $baseImplementation);
    }

    protected function createRepositoryFiles(string $name, array $customMethods)
    {
        $interfacePath = app_path("Repositories/Interfaces/{$name}Repository.php");
        $implPath = app_path("Repositories/Implementation/{$name}RepositoryImpl.php");

        if (File::exists($interfacePath) || File::exists($implPath)) {
            $this->command->error("Repository files for '{$name}' already exist!");
            return;
        }

        $interfaceContent = $this->generateInterface($name, $customMethods);
        $implementationContent = $this->generateImplementation($name, $customMethods);

        File::put($interfacePath, $interfaceContent);
        File::put($implPath, $implementationContent);
        $this->command->info("Repository files for '{$name}' created successfully!");
    }

    protected function generateInterface(string $name, array $customMethods): string
    {
        $methods = empty($customMethods) ? '    // Add custom methods here if needed' :
            collect($customMethods)->map(fn ($method) => "    public function {$method['name']}({$method['params']}): {$method['returnType']};")->implode("\n");

        return <<<EOF
<?php

namespace App\Repositories\Interfaces;

interface {$name}Repository extends BaseRepository
{
{$methods}
}
EOF;
    }

    protected function generateImplementation(string $name, array $customMethods): string
    {
        $methods = empty($customMethods) ? '    // Add custom methods implementation here if needed' :
            collect($customMethods)->map(function ($method) {
                $return = match ($method['returnType']) {
                    'bool' => 'return false;', 'int' => 'return 0;', 'string' => 'return "";',
                    'array' => 'return [];', 'void' => 'return;', 'Model' => 'return $this->model->first();',
                    'Collection' => 'return $this->model->get();', default => 'return null;'
                };
                return "    public function {$method['name']}({$method['params']}): {$method['returnType']}\n    {\n        // TODO: Implement {$method['name']}\n        {$return}\n    }";
            })->implode("\n\n");

        return <<<EOF
<?php

namespace App\Repositories\Implementation;

use App\Models\\{$name};
use App\Repositories\Interfaces\\{$name}Repository;

class {$name}RepositoryImpl extends BaseRepositoryImpl implements {$name}Repository
{
    public function __construct({$name} \$model)
    {
        parent::__construct(\$model);
    }

{$methods}
}
EOF;
    }

    protected function registerRepository(string $name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->createServiceProvider($name);
            $this->command->info("RepositoryServiceProvider created. Register it in bootstrap/app.php.");
            return;
        }

        $content = File::get($providerPath);
        $binding = "\$this->app->bind(\\App\\Repositories\\Interfaces\\{$name}Repository::class, \\App\\Repositories\\Implementation\\{$name}RepositoryImpl::class);";

        if (!str_contains($content, "{$name}Repository::class")) {
            $content = preg_replace('/(public function register\(\).*{)/s', "$1\n        $binding", $content);
            File::put($providerPath, $content);
            $this->command->info("Repository '{$name}' registered in RepositoryServiceProvider.");
        }
    }

    protected function createServiceProvider(string $name)
    {
        $content = <<<EOF
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register()
    {
        \$this->app->bind(\\App\\Repositories\\Interfaces\\{$name}Repository::class, \\App\\Repositories\\Implementation\\{$name}RepositoryImpl::class);
    }
}
EOF;
        File::put(app_path('Providers/RepositoryServiceProvider.php'), $content);
    }
}