<?php

namespace IslamWalied\OneClickProject;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class OneClickProject extends Command
{
    protected $signature = 'generate:project {name}';
    protected $description = 'Create a model, migration, and repository with base repository pattern';
    protected $attributes = [];

    public function handle()
    {
        $name = $this->argument('name');

        if ($this->generateModel($name)) {
            $this->askForCustomMethods();
            $this->generateRepository($name);
            $this->generateService($name);
            $this->generateResource($name);
            $this->generateController($name);
            $this->generateRequestClasses($name);
            $this->createApiFolderStructure();
            $this->generateApiRoutes($name);
            $this->info('Project generation completed!');
        }
    }


    protected function generateModel($name)
    {
        if (File::exists(app_path("Models/{$name}.php"))) {
            $this->error("Model {$name} already exists!");
            return false;
        }

        $this->call('make:model', ['name' => $name]);
        $this->collectModelAttributes();
        $this->createModelStructure($name);

        return true;
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
                'implementInService' => $implementInService
            ];
        }
    }

    protected function collectModelAttributes()
    {

        while (true) {
            $columnName = $this->ask('Enter column name (or "done" to finish):');

            if (strtolower($columnName) === 'done') break;
            if (empty($columnName)) {
                $this->error('Column name cannot be empty.');
                continue;
            }

            $this->attributes[$columnName] = $this->choice('Select column type:', [
                'string', 'integer', 'text', 'boolean', 'date', 'datetime',
                'timestamp', 'float', 'decimal', 'foreignId'
            ]);
        }

    }

    protected function createModelStructure($name)
    {
        $migration = $this->createMigration($name);
        $this->updateMigrationFile($migration, $this->attributes);
        $this->updateModelFile($name, array_keys($this->attributes));
        $this->info("Model {$name} and migration created successfully!");
    }

    protected function createMigration($name)
    {
        $tableName = Str::snake(Str::plural($name));
        $this->call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName
        ]);

        return collect(File::files(database_path('migrations')))
            ->filter(fn($file) => str_contains($file->getFilename(), "create_{$tableName}_table"))
            ->first()
            ->getPathname();
    }

    protected function updateMigrationFile($path, $attributes)
    {
        $schema = collect($attributes)->map(function ($type, $name) {
            return $type === 'foreignId'
                ? "\$table->foreignId('$name')->constrained()->cascadeOnDelete();"
                : "\$table->$type('$name');";
        })->implode("\n            ");

        $content = File::get($path);
        $content = preg_replace(
            '/(\$table->id\(\);)/',
            "$1\n            $schema",
            $content
        );
        File::put($path, $content);
    }

    protected function updateModelFile($name, $fillable)
    {
        $modelPath = app_path("Models/{$name}.php");
        $content = File::get($modelPath);

        $fillableStr = implode("',\n        '", $fillable);
        $fillableProperty = "    protected \$fillable = [\n        '$fillableStr'\n    ];";


        // Generate relationships
        $relationships = $this->generateRelationships();

        $content = preg_replace(
            '/(class\s+' . $name . '\s+extends\s+Model\s*{)/',
            "$1\n$fillableProperty\n$relationships",
            $content
        );

        File::put($modelPath, $content);
    }

    protected function generateRelationships()
    {
        $relationships = '';

        foreach ($this->attributes as $column => $type) {
            if ($type === 'foreignId') {
                $relatedModel = Str::studly(str_replace('_id', '', $column));
                $methodName = Str::camel(str_replace('_id', '', $column));

                $relationships .= <<<EOF

    public function {$methodName}()
    {
        return \$this->belongsTo({$relatedModel}::class);
    }
EOF;
            }
        }

        return $relationships;
    }

    protected function generateRepository($name)
    {
        $this->createRepositoryStructure();
        $this->createBaseRepository();
        $this->createRepositoryFiles($name);
        $this->registerRepository($name);
    }

    protected function generateInterfaceMethods()
    {
        if (empty($this->customMethods)) {
            return '    // Add custom methods here if needed';
        }

        return collect($this->customMethods)->map(function ($method) {
            return "    public function {$method['name']}({$method['params']}): {$method['returnType']};";
        })->implode("\n\n");
    }


    protected function generateImplementation($name)
    {
        $methods = $this->generateImplementationMethods();

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

    protected function generateImplementationMethods()
    {
        if (empty($this->customMethods)) {
            return '    // Add custom methods implementation here if needed';
        }

        return collect($this->customMethods)->map(function ($method) {
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
        // Base Repository Interface
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

        // Base Repository Implementation
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
        return $this->model->save();
    }

    public function delete($model)
    {
        return $this->model->delete();
    }
}
EOF;

        File::put(app_path('Repositories/Interfaces/BaseRepository.php'), $baseInterface);
        File::put(app_path('Repositories/Implementation/BaseRepositoryImpl.php'), $baseImplementation);
    }

    protected function createRepositoryFiles($name)
    {
        // Generate interface with custom methods
        $interfaceContent = $this->generateInterface($name);

        // Generate implementation with custom methods
        $implementationContent = $this->generateImplementation($name);

        File::put(app_path("Repositories/Interfaces/{$name}Repository.php"), $interfaceContent);
        File::put(app_path("Repositories/Implementation/{$name}RepositoryImpl.php"), $implementationContent);
    }

    protected function generateInterface($name)
    {
        $methods = $this->generateInterfaceMethods();

        return <<<EOF
<?php

namespace App\Repositories\Interfaces;

interface {$name}Repository extends BaseRepository
{
{$methods}
}
EOF;
    }

    protected function registerRepository($name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->createServiceProvider($name);
            $this->info('Don\'t forget to register RepositoryServiceProvider in bootstrap/app.php');
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

    protected function createServiceProvider($name)
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

    protected function generateService($name)
    {
        $this->createServiceStructure();
        $this->createBaseService();
        $this->createServiceFiles($name);
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

class BaseServiceImpl
{
    // Implement base service methods here if needed
}
EOF;

        File::put(app_path('Services/Implementation/BaseServiceImpl.php'), $baseImplementation);
    }

    protected function createServiceFiles($name)
    {
        // Generate interface with custom methods
        $interfaceContent = $this->generateServiceInterface($name);

        // Generate implementation with custom methods
        $implementationContent = $this->generateServiceImplementation($name);

        File::put(app_path("Services/Interfaces/{$name}Service.php"), $interfaceContent);
        File::put(app_path("Services/Implementation/{$name}ServiceImpl.php"), $implementationContent);
    }

    protected function generateServiceInterface($name)
    {
        $lower = strtolower($name);
        $methods = [];

        $methods[] = '    public function index($limit);';
        $methods[] = '    public function show($' . $lower . ');';
        $methods[] = '    public function store($request);';
        $methods[] = '    public function update($request, $' . $lower . ');';
        $methods[] = '    public function delete($' . $lower . ');';

        // Add custom methods if they should be implemented in the service
        if (!empty($this->customMethods)) {
            foreach ($this->customMethods as $method) {
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

    protected function generateServiceImplementation($name)
    {
        $methods = [];
        $lowerName = strtolower($name);
        $methods[] = $this->generateIndexMethod($name);
        $methods[] = $this->generateShowMethod($name);
        $methods[] = $this->generateStoreMethod($name);
        $methods[] = $this->generateUpdateMethod($name);
        $methods[] = $this->generateDeleteMethod($name);


        if (!empty($this->customMethods)) {
            foreach ($this->customMethods as $method) {
                if ($method['implementInService']) {
                    $methods[] = $this->generateCustomServiceMethod($method);
                }
            }
        }

        $methodsStr = implode("\n\n", $methods);

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

    protected function generateIndexMethod($name)
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

    protected function generateShowMethod($name)
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

    protected function generateStoreMethod($name)
    {
        $lower = strtolower($name);

        // Check if any of the specified image attributes exist in the model
        $imageAttributes = ['image', 'photo', 'plan_image'];
        $hasImageAttribute = false;
        $imageAttributeName = null;

        foreach ($imageAttributes as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $hasImageAttribute = true;
                $imageAttributeName = $attribute; // Store the first matching attribute name
                break;
            }
        }

        $saveImageLogic = '';
        if ($hasImageAttribute) {
            $saveImageLogic = <<<CODE
\${$lower}Image = \$this->saveImage(\$request, '{$imageAttributeName}', '{$name}/Images');
CODE;
        }

        $dataArray = [];
        foreach ($this->attributes as $attribute => $type) {
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

    protected function generateUpdateMethod($name)
    {
        $lower = strtolower($name);

        // Check if any of the specified image attributes exist in the model
        $imageAttributes = ['image', 'photo', 'plan_image'];
        $hasImageAttribute = false;
        $imageAttributeName = null;

        foreach ($imageAttributes as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $hasImageAttribute = true;
                $imageAttributeName = $attribute;
                break;
            }
        }

        $updateImageLogic = '';
        if ($hasImageAttribute) {
            $updateImageLogic = <<<CODE
\${$lower}Image = \$this->updateImage(\$request, '{$imageAttributeName}', '{$name}/Images', \${$lower}->{$imageAttributeName});
CODE;
        }

        $attributeUpdates = [];
        foreach ($this->attributes as $attribute => $type) {
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

    protected function generateDeleteMethod($name)
    {
        $lower = strtolower($name);

        // Check if any of the specified attributes exist in the model
        $attributes = ['image', 'image_url', 'photo', 'icon'];
        $hasImageAttribute = false;

        foreach ($attributes as $attribute) {
            if (isset($this->attributes[$attribute])) {
                $hasImageAttribute = true;
                $name = $attribute;
                break;
            }
        }

        $deleteImageLogic = '';
        if ($hasImageAttribute) {
            $deleteImageLogic = <<<CODE
\$this->deleteImage(\${$lower}->{$name});
CODE;
        }

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

    protected function generateCustomServiceMethod($method)
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

    protected function getImageAttribute($model): ?string
    {
        $imageAttributes = ['image', 'image_url', 'photo', 'icon'];

        foreach ($imageAttributes as $attribute) {
            if (isset($model->$attribute)) {
                return $attribute;
            }
        }

        return null;
    }

    protected function makeServiceProvider($name)
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

    protected function registerService($name)
    {
        $providerPath = app_path('Providers/ServiceServiceProvider.php');

        if (!File::exists($providerPath)) {
            $this->makeServiceProvider($name);
            $this->info('Don\'t forget to register ServiceServiceProvider in bootstrap/app.php');
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

    protected function generateResource($name)
    {
        $resourceDirectory = app_path('Http/Resources');

        // Create the Resources directory if it doesn't exist
        if (!File::exists($resourceDirectory)) {
            File::makeDirectory($resourceDirectory, 0755, true);
        }

        $resourcePath = "{$resourceDirectory}/{$name}Resource.php";

        if (File::exists($resourcePath)) {
            $this->error("Resource {$name}Resource already exists!");
            return;
        }

        // Generate the Resource class content
        $resourceContent = $this->generateResourceContent($name);

        // Create the Resource file
        File::put($resourcePath, $resourceContent);

        $this->info("Resource {$name}Resource created successfully!");
    }

    protected function generateResourceContent($name)
    {
        // Get the model's attributes
        $attributes = array_keys($this->attributes);

        // Generate the attribute mapping for the `toArray` method
        $attributeMappings = [];
        foreach ($attributes as $attribute) {
            $attributeMappings[] = "'{$attribute}' => \$this->{$attribute},";
        }
        $attributeMappingsStr = implode("\n            ", $attributeMappings);

        return <<<EOF
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {$name}Resource extends JsonResource
{
    public function toArray(\$request)
    {
        return [
            {$attributeMappingsStr}
        ];
    }
}
EOF;
    }
    protected function generateController($name)
    {
        $this->call('make:controller', [
            'name' => "{$name}Controller",
            '--model' => $name
        ]);

        $this->updateControllerFile($name);
    }

    protected function updateControllerFile($name)
    {
        $controllerPath = app_path("Http/Controllers/{$name}Controller.php");
        $lower = strtolower($name);
        $serviceInterface = "{$name}Service";

        $updatedContent = <<<EOF
<?php

namespace App\Http\Controllers;

use App\Models\\{$name};
use App\Services\Interfaces\\{$serviceInterface};
use App\Http\Requests\Store{$name}Request;
use App\Http\Requests\Update{$name}Request;
use Illuminate\Http\JsonResponse;

class {$name}Controller extends Controller
{
    protected {$serviceInterface} \${$lower}Service;

    public function __construct({$serviceInterface} \${$lower}Service)
    {
        \$this->{$lower}Service = \${$lower}Service;
    }

    public function index(): JsonResponse
    {
        return \$this->{$lower}Service->index(request('limit', 10));
    }

    public function show({$name} \${$lower}): JsonResponse
    {
        return \$this->{$lower}Service->show(\${$lower});
    }

    public function store(Store{$name}Request \$request): JsonResponse
    {
        return \$this->{$lower}Service->store(\$request);
    }

    public function update(Update{$name}Request \$request, {$name} \${$lower}): JsonResponse
    {
        return \$this->{$lower}Service->update(\$request, \${$lower});
    }

    public function destroy({$name} \${$lower}): JsonResponse
    {
        return \$this->{$lower}Service->delete(\${$lower});
    }
}
EOF;

        File::put($controllerPath, $updatedContent);
    }
    protected function generateRequestClasses($name)
    {
        $requestDirectory = app_path('Http/Requests');

        if (!File::exists($requestDirectory)) {
            File::makeDirectory($requestDirectory, 0755, true);
        }

        $storeRequestPath = "{$requestDirectory}/Store{$name}Request.php";
        $storeRequestContent = $this->generateStoreRequestContent($name);
        File::put($storeRequestPath, $storeRequestContent);

        $updateRequestPath = "{$requestDirectory}/Update{$name}Request.php";
        $updateRequestContent = $this->generateUpdateRequestContent($name);
        File::put($updateRequestPath, $updateRequestContent);
    }
    protected function generateForeignKeyValidationRule($attribute)
    {
        $tableName = Str::plural(str_replace('_id', '', $attribute));
        return "'$attribute' => 'required|numeric|exists:{$tableName},id'";
    }
    protected function generateStoreRequestContent($name)
    {
        $rules = [];
        $processedAttributes = [];

        foreach ($this->attributes as $attribute => $type) {
            // Skip if already processed
            if (in_array($attribute, $processedAttributes)) {
                continue;
            }

            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                $rules[] = "'$attribute' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120'";
                $processedAttributes[] = $attribute;
                continue;
            }

            $rule = match($type) {
                'string' => "'$attribute' => 'required|string|max:255'",
                'integer' => "'$attribute' => 'required|integer'",
                'boolean' => "'$attribute' => 'required|boolean'",
                'date' => "'$attribute' => 'required|date'",
                'foreignId' => $this->generateForeignKeyValidationRule($attribute),
                default => "'$attribute' => 'required'"
            };

            $rules[] = $rule;
            $processedAttributes[] = $attribute;
        }

        $rulesStr = implode(",\n            ", $rules);

        return <<<EOF
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{$name}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            {$rulesStr}
        ];
    }
}
EOF;
    }

    protected function generateForeignKeyUpdateValidationRule($attribute)
    {
        // Remove '_id' suffix and convert to plural
        $tableName = Str::plural(str_replace('_id', '', $attribute));
        return "'$attribute' => 'sometimes|numeric|exists:{$tableName},id'";
    }

    protected function generateUpdateRequestContent($name)
    {
        $rules = [];
        $processedAttributes = [];

        foreach ($this->attributes as $attribute => $type) {
            // Skip if already processed
            if (in_array($attribute, $processedAttributes)) {
                continue;
            }

            // Handle image-specific attributes
            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                $rules[] = "'$attribute' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:5120'";
                $processedAttributes[] = $attribute;
                continue;
            }

            // Generate rule based on type
            $rule = match($type) {
                'string' => "'$attribute' => 'sometimes|string|max:255'",
                'integer' => "'$attribute' => 'sometimes|integer'",
                'boolean' => "'$attribute' => 'sometimes|boolean'",
                'date' => "'$attribute' => 'sometimes|date'",
                'foreignId' => $this->generateForeignKeyUpdateValidationRule($attribute),
                default => "'$attribute' => 'sometimes'"
            };

            $rules[] = $rule;
            $processedAttributes[] = $attribute;
        }

        $rulesStr = implode(",\n            ", $rules);

        return <<<EOF
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{$name}Request extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            {$rulesStr}
        ];
    }
}
EOF;
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
    protected function generateApiRoutes($name)
    {
        $lower = strtolower($name);
        $routesPath = base_path("routes/api/{$lower}.php");

        if (File::exists($routesPath)) {
            $this->error("API Routes file for {$name} already exists!");
            return;
        }

        $routesContent = $this->getRoutesTemplate($name);
        File::put($routesPath, $routesContent);

        $this->info("API Routes file for {$name} created successfully!");
    }
    protected function getRoutesTemplate($name)
    {
        $pluralName = Str::plural(strtolower($name));
        $snakeCaseLower = Str::snake($name);
        return <<<EOF
<?php
use App\Http\Controllers\\{$name}Controller;
use Illuminate\Support\Facades\Route;


Route::middleware(['cors','lang'])->prefix('v1/')->group(function () {
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
}
