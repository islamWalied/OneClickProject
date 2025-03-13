<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ResourceGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array $attributes)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid resource name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        $resourceDirectory = app_path('Http/Resources');
        if (!File::exists($resourceDirectory)) {
            File::makeDirectory($resourceDirectory, 0755, true);
        }

        $resourcePath = "{$resourceDirectory}/{$name}Resource.php";
        if (File::exists($resourcePath)) {
            $this->command->error("Resource '{$name}Resource' already exists at '{$resourcePath}'!");
            return;
        }

        $resourceContent = $this->generateResourceContent($name, $attributes);
        File::put($resourcePath, $resourceContent);
        $this->command->info("Resource '{$name}Resource' created successfully!");
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    protected function generateResourceContent(string $name, array $attributes): string
    {
        if (empty($attributes)) {
            $this->command->warn("No attributes provided for '{$name}' resource. Only 'id' will be included.");
        }

        $attributeMappings = collect(array_keys($attributes))->map(fn ($attribute) =>
        !$this->isValidName($attribute) ? null : "'{$attribute}' => \$this->{$attribute},"
        )->filter()->implode("\n            ");

        return <<<EOF
<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {$name}Resource extends JsonResource
{
    public function toArray(\$request)
    {
        return [
            'id' => \$this->id,
            {$attributeMappings}
        ];
    }
}
EOF;
    }
}