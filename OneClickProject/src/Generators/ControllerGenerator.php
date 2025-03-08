<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ControllerGenerator
{
    protected Command $command;
    protected array $attributes = []; // Initialize as an empty array

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name)
    {
        $this->command->call('make:controller', [
            'name' => "{$name}Controller",
            '--model' => $name,
        ]);

        $this->updateControllerFile($name);
    }

    protected function updateControllerFile(string $name)
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
        return \$this->{$lower}Service->index(request('per_page', 10));
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
        $this->command->info("Controller {$name}Controller updated successfully!");
    }
}