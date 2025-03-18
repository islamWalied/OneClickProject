<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ServiceGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array $customMethods, array $attributes)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid service name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        foreach ($customMethods as $method) {
            if (!isset($method['name']) || !$this->isValidName($method['name'])) {
                $this->command->error("Invalid custom method name '{$method['name']}'. Skipping generation.");
                return;
            }
        }

        $this->createServiceStructure();
        $this->createBaseService();
        $this->createServiceFiles($name, $customMethods, $attributes);
        $this->registerService($name);
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $name) === 1;
    }

    protected function createServiceStructure()
    {
        foreach (['Services/Implementation', 'Services/Interfaces'] as $dir) {
            $path = app_path($dir);
            if (!File::exists($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }

    protected function createBaseService()
    {
        $basePath = app_path('Services/Implementation/BaseServiceImpl.php');
        if (File::exists($basePath)) {
            $this->command->warn("Base service file already exists. Skipping creation.");
            return;
        }

        $content = <<<EOF
<?php

namespace App\Services\Implementation;

use App\Traits\ImageTrait;
use App\Traits\ResponseTrait;

class BaseServiceImpl
{
    use ResponseTrait, ImageTrait;
}
EOF;
        File::put($basePath, $content);
    }

    protected function createServiceFiles(string $name, array $customMethods, array $attributes)
    {
        $interfacePath = app_path("Services/Interfaces/{$name}Service.php");
        $implPath = app_path("Services/Implementation/{$name}ServiceImpl.php");

        if (File::exists($interfacePath) || File::exists($implPath)) {
            $this->command->error("Service files for '{$name}' already exist!");
            return;
        }

        $interfaceContent = $this->generateServiceInterface($name, $customMethods);
        $implementationContent = $this->generateServiceImplementation($name, $customMethods, $attributes);

        File::put($interfacePath, $interfaceContent);
        File::put($implPath, $implementationContent);
        $this->command->info("Service files for '{$name}' created successfully!");
    }

    protected function generateServiceInterface(string $name, array $customMethods): string
    {
        $lower = strtolower($name);
        $methods = [
            "    public function index(\$limit);",
            "    public function show(\${$lower});",
            "    public function store(\$request);",
            "    public function update(\$request, \${$lower});",
            "    public function delete(\${$lower});",
        ];

        if (!empty($customMethods)) {
            foreach ($customMethods as $method) {
                if ($method['implementInService']) {
                    $methods[] = "    public function {$method['name']}({$method['params']}): {$method['returnType']};";
                }
            }
        }

        return <<<EOF
<?php

namespace App\Services\Interfaces;

interface {$name}Service
{
{$this->implodeMethods($methods)}
}
EOF;
    }

    protected function generateServiceImplementation(string $name, array $customMethods, array $attributes): string
    {
        $methods = [
            $this->generateIndexMethod($name),
            $this->generateShowMethod($name),
            $this->generateStoreMethod($name, $attributes),
            $this->generateUpdateMethod($name, $attributes),
            $this->generateDeleteMethod($name, $attributes),
        ];

        if (!empty($customMethods)) {
            foreach ($customMethods as $method) {
                if ($method['implementInService']) {
                    $methods[] = $this->generateCustomServiceMethod($method, $name);
                }
            }
        }

        $lowerName = strtolower($name);
        return <<<EOF
<?php

namespace App\Services\Implementation;

use App\Repositories\Interfaces\\{$name}Repository;
use App\Services\Interfaces\\{$name}Service;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\\{$name}Resource;

class {$name}ServiceImpl extends BaseServiceImpl implements {$name}Service
{
    protected {$name}Repository \${$lowerName}Repository;

    public function __construct({$name}Repository \${$lowerName}Repository)
    {
        \$this->{$lowerName}Repository = \${$lowerName}Repository;
    }

{$this->implodeMethods($methods)}
}
EOF;
    }

    protected function implodeMethods(array $methods): string
    {
        return implode("\n\n", $methods);
    }

    protected function generateIndexMethod(string $name): string
    {
        $lower = strtolower($name);
        return <<<METHOD
    public function index(\$limit)
    {
        try {
            Log::info('{$name} index request', ['limit' => \$limit]);
            \${$lower} = \$this->{$lower}Repository->index(\$limit);
            Log::info('{$name} index successful', ['count' => \${$lower}->count()]);
            return \$this->returnData(__('messages.{$lower}.index_success'), 200, {$name}Resource::collection(\${$lower}));
        } catch (\Exception \$e) {
            Log::error('{$name} fetch Error: ', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.index_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateShowMethod(string $name): string
    {
        $lower = strtolower($name);
        return <<<METHOD
    public function show(\${$lower})
    {
        try {
            Log::info('{$name} show request', ['id' => \${$lower}->id]);
            \${$lower} = \$this->{$lower}Repository->show(\${$lower}->id);
            Log::info('{$name} show successful', ['id' => \${$lower}->id]);
            return \$this->returnData(__('messages.{$lower}.show_success'), 200, new {$name}Resource(\${$lower}));
        } catch (\Exception \$e) {
            Log::error('{$name} fetch Error:', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.show_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateStoreMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'photo', 'plan_image', 'icon'];
        $imageAttribute = array_key_first(array_intersect(array_keys($attributes), $imageAttributes));

        $imageLogic = $imageAttribute ? "\${$lower}Image = \$this->saveImage(\$request, '{$imageAttribute}', '{$name}/Images');" : '';
        $dataArray = collect($attributes)->map(function ($options, $attr) use ($imageAttribute, $lower) {
            return $attr === $imageAttribute
                ? "                \"{$attr}\" => \${$lower}Image,"
                : "                \"{$attr}\" => \$request->$attr,";
        })->implode("\n");

        return <<<METHOD
    public function store(\$request)
    {
        try {
            Log::info('{$name} store request', \$request->all());
            {$imageLogic}
            \${$lower} = [
{$dataArray}
            ];
            \$created{$name} = \$this->{$lower}Repository->store(\${$lower});
            Log::info('{$name} created successfully', ['id' => \$created{$name}->id]);
            return \$this->success(__('messages.{$lower}.create_success'), 201);
        } catch (\Exception \$e) {
            Log::error('{$name} Create Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.create_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateUpdateMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'photo', 'plan_image', 'icon'];
        $imageAttribute = array_key_first(array_intersect(array_keys($attributes), $imageAttributes));

        $imageLogic = $imageAttribute ? "\${$lower}Image = \$this->updateImage(\$request, '{$imageAttribute}', '{$name}/Images', \${$lower}->{$imageAttribute});" : '';
        $attributeUpdates = collect($attributes)->map(function ($options, $attr) use ($imageAttribute, $lower) {
            return $attr === $imageAttribute
                ? "            \${$lower}->{$attr} = \${$lower}Image ?? \${$lower}->{$attr};"
                : "            \${$lower}->{$attr} = \$request->$attr ?? \${$lower}->{$attr};";
        })->implode("\n");

        return <<<METHOD
    public function update(\$request, \${$lower})
    {
        try {
            Log::info('{$name} update request', ['id' => \${$lower}->id, 'data' => \$request->all()]);
            {$imageLogic}
{$attributeUpdates}
            \$this->{$lower}Repository->update(\${$lower});
            Log::info('{$name} updated successfully', ['id' => \${$lower}->id]);
            return \$this->success(__('messages.{$lower}.update_success'), 200);
        } catch (\Exception \$e) {
            Log::error('{$name} Update Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.update_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateDeleteMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'photo', 'plan_image', 'icon'];
        $imageAttribute = array_key_first(array_intersect(array_keys($attributes), $imageAttributes));
        $deleteImageLogic = $imageAttribute ? "\$this->deleteImage(\${$lower}->{$imageAttribute});" : '';

        return <<<METHOD
    public function delete(\${$lower})
    {
        try {
            Log::info('{$name} delete request', ['id' => \${$lower}->id]);
            {$deleteImageLogic}
            \$this->{$lower}Repository->delete(\${$lower});
            Log::info('{$name} deleted successfully', ['id' => \${$lower}->id]);
            return \$this->success(__('messages.{$lower}.delete_success'), 204);
        } catch (\Exception \$e) {
            Log::error('{$name} Delete Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.delete_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateCustomServiceMethod(array $method, string $name): string
    {
        $return = match ($method['returnType']) {
            'bool' => 'return false;', 'int' => 'return 0;', 'string' => 'return "";',
            'array' => 'return [];', 'void' => 'return;', 'Model' => 'return $this->model->first();',
            'Collection' => 'return $this->model->get();', default => 'return null;'
        };

        $paramsForLog = preg_replace('/\w+\s+(\$\w+)/', '$1', $method['params']);
        $paramsArray = empty($method['params']) ? '' : '[' . implode(', ', array_map(fn($param) => "'$param' => $param", explode(', ', $paramsForLog))) . ']';

        return <<<METHOD
    public function {$method['name']}({$method['params']}): {$method['returnType']}
    {
        Log::info('{$name} custom method {$method['name']} called', {$paramsArray});
        // TODO: Implement {$method['name']}
        {$return}
    }
METHOD;
    }

    protected function registerService(string $name)
    {
        $providerPath = app_path('Providers/ServiceServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->makeServiceProvider($name);
            $this->command->info("ServiceServiceProvider created. Register it in bootstrap/app.php.");
            return;
        }

        $content = File::get($providerPath);
        $binding = "\$this->app->bind(\\App\\Services\\Interfaces\\{$name}Service::class, \\App\\Services\\Implementation\\{$name}ServiceImpl::class);";

        if (!str_contains($content, "{$name}Service::class")) {
            $content = preg_replace('/(public function register\(\).*{)/s', "$1\n        $binding", $content);
            File::put($providerPath, $content);
            $this->command->info("Service '{$name}' registered in ServiceServiceProvider.");
        }
    }

    protected function makeServiceProvider(string $name)
    {
        $content = <<<EOF
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class ServiceServiceProvider extends ServiceProvider
{
    public function register()
    {
        \$this->app->bind(\\App\\Services\\Interfaces\\{$name}Service::class, \\App\\Services\\Implementation\\{$name}ServiceImpl::class);
    }
}
EOF;
        File::put(app_path('Providers/ServiceServiceProvider.php'), $content);
    }
}