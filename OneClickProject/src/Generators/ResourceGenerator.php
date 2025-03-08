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
        $resourceDirectory = app_path('Http/Resources');

        if (!File::exists($resourceDirectory)) {
            File::makeDirectory($resourceDirectory, 0755, true);
        }

        $resourcePath = "{$resourceDirectory}/{$name}Resource.php";

        if (File::exists($resourcePath)) {
            $this->command->error("Resource {$name}Resource already exists!");
            return;
        }

        $resourceContent = $this->generateResourceContent($name, $attributes);
        File::put($resourcePath, $resourceContent);

        $this->command->info("Resource {$name}Resource created successfully!");
    }

    protected function generateResourceContent(string $name, array $attributes): string
    {
        $attributeMappings = [];
        foreach (array_keys($attributes) as $attribute) {
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
            'id' => \$this->id,
            {$attributeMappingsStr}
        ];
    }
}
EOF;
    }
}