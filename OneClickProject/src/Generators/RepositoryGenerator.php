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
        $this->createRepositoryStructure();
        $this->createBaseRepository();
        $this->createRepositoryFiles($name, $customMethods);
        $this->registerRepository($name);
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
        $baseInterface = <<<'EOF'
<?php

namespace App\Repositories\Interfaces;

interface BaseRepository
{
    public function index($limit);
    public function show($model);
    public function findWhere(array $criteria);
    public function store(array $data);
    public function update($model);
    public function delete($model);
}
EOF;

        $baseImplementation = <<<'EOF'
<?php

namespace App\Repositories\Implementation;

use App\Repositories\Interfaces\BaseRepository;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepositoryImpl implements BaseRepository
{
    protected $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function index($limit)
    {
        return $this->model->paginate($limit);
    }

    public function show($model)
    {
        return $this->model->findOrFail($model);
    }

    public function findWhere(array $criteria)
    {
        return $this->model->where($criteria)->get();
    }

    public function store(array $data)
    {
        return $this->model->create($data);
    }

    public function update($model)
    {
        return $model->save();
    }

    public function delete($model)
    {
        return $model->delete();
    }
}
EOF;

        File::put(app_path('Repositories/Interfaces/BaseRepository.php'), $baseInterface);
        File::put(app_path('Repositories/Implementation/BaseRepositoryImpl.php'), $baseImplementation);
    }

    protected function createRepositoryFiles(string $name, array $customMethods)
    {
        $interfaceContent = $this->generateInterface($name, $customMethods);
        $implementationContent = $this->generateImplementation($name, $customMethods);

        File::put(app_path("Repositories/Interfaces/{$name}Repository.php"), $interfaceContent);
        File::put(app_path("Repositories/Implementation/{$name}RepositoryImpl.php"), $implementationContent);
    }

    protected function generateInterface(string $name, array $customMethods): string
    {
        $methods = $this->generateInterfaceMethods($customMethods);

        return <<<EOF
<?php

namespace App\Repositories\Interfaces;

interface {$name}Repository extends BaseRepository
{
{$methods}
}
EOF;
    }

    protected function generateInterfaceMethods(array $customMethods): string
    {
        if (empty($customMethods)) {
            return '    // Add custom methods here if needed';
        }

        return collect($customMethods)->map(function ($method) {
            return "    public function {$method['name']}({$method['params']}): {$method['returnType']};";
        })->implode("\n\n");
    }

    protected function generateImplementation(string $name, array $customMethods): string
    {
        $methods = $this->generateImplementationMethods($customMethods);

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

    protected function generateImplementationMethods(array $customMethods): string
    {
        if (empty($customMethods)) {
            return '    // Add custom methods implementation here if needed';
        }

        return collect($customMethods)->map(function ($method) {
            $returnStatement = match ($method['returnType']) {
                'bool' => 'return false;',
                'int' => 'return 0;',
                'string' => 'return "";',
                'array' => 'return [];',
                'void' => 'return;',
                'Model' => 'return $this->model->first();',
                'Collection' => 'return $this->model->get();',
                default => 'return null;'
            };

            return <<<METHOD
    public function {$method['name']}({$method['params']}): {$method['returnType']}
    {
        // TODO: Implement {$method['name']} method
        {$returnStatement}
    }
METHOD;
        })->implode("\n\n");
    }

    protected function registerRepository(string $name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->createServiceProvider($name);
            $this->command->info('Don\'t forget to register RepositoryServiceProvider in bootstrap/app.php');
            return;
        }

        $binding = "\$this->app->bind(\\App\\Repositories\\Interfaces\\{$name}Repository::class, \\App\\Repositories\\Implementation\\{$name}RepositoryImpl::class);";
        $content = File::get($providerPath);

        if (!str_contains($content, "{$name}Repository::class")) {
            $content = preg_replace(
                '/(public function register\(\).*{)/s',
                "$1\n        $binding",
                $content
            );
            File::put($providerPath, $content);
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