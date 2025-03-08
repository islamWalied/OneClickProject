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
        $this->createServiceStructure();
        $this->createBaseService();
        $this->createServiceFiles($name, $customMethods, $attributes);
        $this->registerService($name);
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
        $baseImplementation = <<<'EOF'
<?php

namespace App\Services\Implementation;

use App\Traits\ImageTrait;
use App\Traits\ResponseTrait;

class BaseServiceImpl
{
    use ResponseTrait, ImageTrait;
}
EOF;

        File::put(app_path('Services/Implementation/BaseServiceImpl.php'), $baseImplementation);
    }

    protected function createServiceFiles(string $name, array $customMethods, array $attributes)
    {
        $interfaceContent = $this->generateServiceInterface($name, $customMethods);
        $implementationContent = $this->generateServiceImplementation($name, $customMethods, $attributes);

        File::put(app_path("Services/Interfaces/{$name}Service.php"), $interfaceContent);
        File::put(app_path("Services/Implementation/{$name}ServiceImpl.php"), $implementationContent);
    }

    protected function generateServiceInterface(string $name, array $customMethods): string
    {
        $lower = strtolower($name);
        $methods = [
            '    public function index($limit);',
            "    public function show(\${$lower});",
            '    public function store($request);',
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

        $methodsStr = implode("\n", $methods);

        return <<<EOF
<?php

namespace App\Services\Interfaces;

interface {$name}Service
{
{$methodsStr}
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
                    $methods[] = $this->generateCustomServiceMethod($method);
                }
            }
        }

        $methodsStr = implode("\n\n", $methods);
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

{$methodsStr}
}
EOF;
    }

    protected function generateIndexMethod(string $name): string
    {
        $lower = strtolower($name);
        return <<<METHOD
    public function index(\$limit)
    {
        try {
            \${$lower} = \$this->{$lower}Repository->index(\$limit);
            return \$this->returnData(
                __('messages.{$lower}.index_success'), 200,
                {$name}Resource::collection(\${$lower})
            );
        } catch (\\Exception \$e) {
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
            \${$lower} = \$this->{$lower}Repository->show(\${$lower}->id);
            return \$this->returnData(
                __('messages.{$lower}.show_success'), 200,
                new {$name}Resource(\${$lower})
            );
        } catch (\\Exception \$e) {
            Log::error('{$name} fetch Error:', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.show_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateStoreMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'photo', 'plan_image'];
        $hasImageAttribute = false;
        $imageAttributeName = null;

        foreach ($imageAttributes as $attribute) {
            if (isset($attributes[$attribute])) {
                $hasImageAttribute = true;
                $imageAttributeName = $attribute;
                break;
            }
        }

        $saveImageLogic = $hasImageAttribute ? "\${$lower}Image = \$this->saveImage(\$request, '{$imageAttributeName}', '{$name}/Images');" : '';

        $dataArray = [];
        foreach ($attributes as $attribute => $type) {
            if ($attribute === $imageAttributeName) {
                $dataArray[] = "\"{$attribute}\" => \${$lower}Image,";
            } else {
                $dataArray[] = "\"{$attribute}\" => \$request->{$attribute},";
            }
        }
        $dataArrayStr = implode("\n                ", $dataArray);

        return <<<METHOD
    public function store(\$request)
    {
        try {
            {$saveImageLogic}

            \${$lower} = [
                {$dataArrayStr}
            ];

            \$this->{$lower}Repository->store(\${$lower});

            return \$this->success(__('messages.{$lower}.create_success'), 201);
        } catch (\\Exception \$e) {
            Log::error('{$name} Create Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.create_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateUpdateMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'photo', 'plan_image'];
        $hasImageAttribute = false;
        $imageAttributeName = null;

        foreach ($imageAttributes as $attribute) {
            if (isset($attributes[$attribute])) {
                $hasImageAttribute = true;
                $imageAttributeName = $attribute;
                break;
            }
        }

        $updateImageLogic = $hasImageAttribute ? "\${$lower}Image = \$this->updateImage(\$request, '{$imageAttributeName}', '{$name}/Images', \${$lower}->{$imageAttributeName});" : '';

        $attributeUpdates = [];
        foreach ($attributes as $attribute => $type) {
            if ($attribute === $imageAttributeName) {
                $attributeUpdates[] = "\${$lower}->{$attribute} = \${$lower}Image ?? \${$lower}->{$attribute};";
            } else {
                $attributeUpdates[] = "\${$lower}->{$attribute} = \$request->{$attribute} ?? \${$lower}->{$attribute};";
            }
        }
        $attributeUpdatesStr = implode("\n            ", $attributeUpdates);

        return <<<METHOD
    public function update(\$request, \${$lower})
    {
        try {
            {$updateImageLogic}

            {$attributeUpdatesStr}

            \$this->{$lower}Repository->update(\${$lower});

            return \$this->success(__('messages.{$lower}.update_success'), 200);
        } catch (\\Exception \$e) {
            Log::error('{$name} Update Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.update_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateDeleteMethod(string $name, array $attributes): string
    {
        $lower = strtolower($name);
        $imageAttributes = ['image', 'image_url', 'photo', 'icon'];
        $hasImageAttribute = false;
        $imageAttributeName = null;

        foreach ($imageAttributes as $attribute) {
            if (isset($attributes[$attribute])) {
                $hasImageAttribute = true;
                $imageAttributeName = $attribute;
                break;
            }
        }

        $deleteImageLogic = $hasImageAttribute ? "\$this->deleteImage(\${$lower}->{$imageAttributeName});" : '';

        return <<<METHOD
    public function delete(\${$lower})
    {
        try {
            {$deleteImageLogic}
            \$this->{$lower}Repository->delete(\${$lower});

            return \$this->success(__('messages.{$lower}.delete_success'), 204);
        } catch (\\Exception \$e) {
            Log::error('{$name} Delete Error', ['error' => \$e->getMessage()]);
            \$this->returnError(__('messages.{$lower}.delete_failed'), 500);
        }
    }
METHOD;
    }

    protected function generateCustomServiceMethod(array $method): string
    {
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
    }

    protected function registerService(string $name)
    {
        $providerPath = app_path('Providers/ServiceServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->makeServiceProvider($name);
            $this->command->info('Don\'t forget to register ServiceServiceProvider in bootstrap/app.php');
            return;
        }

        $binding = "\$this->app->bind(\\App\\Services\\Interfaces\\{$name}Service::class, \\App\\Services\\Implementation\\{$name}ServiceImpl::class);";
        $content = File::get($providerPath);

        if (!str_contains($content, "{$name}Service::class")) {
            $content = preg_replace(
                '/(public function register\(\).*{)/s',
                "$1\n        $binding",
                $content
            );
            File::put($providerPath, $content);
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