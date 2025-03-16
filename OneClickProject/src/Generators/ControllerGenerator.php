<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ControllerGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid controller name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        $controllerPath = app_path("Http/Controllers/{$name}Controller.php");
        if (File::exists($controllerPath)) {
            $this->command->error("Controller '{$name}Controller' already exists at '{$controllerPath}'!");
            return;
        }

        $this->createControllerFile($controllerPath, $name);
        $this->command->info("Controller '{$name}Controller' created successfully!");
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $name) === 1;
    }

    protected function createControllerFile(string $path, string $name)
    {
        $lower = strtolower($name);
        $serviceInterface = "{$name}Service";
        $content = <<<EOF
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
        File::put($path, $content);
    }
}