<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelGenerator
{
    protected Command $command;
    protected array $attributes = [];

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array &$attributes): bool
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid model name '{$name}'. Use alphanumeric characters and start with a letter.");
            return false;
        }

        $modelPath = app_path("Models/{$name}.php");
        if (File::exists($modelPath)) {
            $this->command->error("Model '{$name}' already exists at '{$modelPath}'!");
            return false;
        }

        $this->createModelFile($modelPath, $name);
        $this->collectModelAttributes($attributes);
        $this->updateModelFile($modelPath, $name, array_keys($attributes));

        return true;
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    protected function createModelFile(string $path, string $name)
    {
        $stub = <<<EOF
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class {$name} extends Model
{
}
EOF;
        File::put($path, $stub);
    }

    protected function collectModelAttributes(array &$attributes)
    {
        $columnTypes = ['string', 'integer', 'text', 'boolean', 'date', 'datetime', 'timestamp', 'float', 'decimal', 'foreignId', 'enum'];

        while (true) {
            $columnName = $this->command->ask('Enter column name (or "done" to finish):');
            if (strtolower($columnName) === 'done') {
                break;
            }
            if (!$this->isValidName($columnName)) {
                $this->command->error("Invalid column name '{$columnName}'. Use alphanumeric characters and start with a letter.");
                continue;
            }
            if (isset($attributes[$columnName])) {
                $this->command->error("Column '{$columnName}' already defined. Skipping duplicate.");
                continue;
            }

            $type = $this->command->choice("Select column type for '{$columnName}':", $columnTypes);
            $options = ['type' => $type];

            $modifiers = $this->command->choice(
                "Select modifiers for '{$columnName}' (multiple choice, comma-separated, press Enter for none):",
                ['nullable', 'unique', 'default', 'none'],
                null,
                null,
                true
            );
            $modifiers = is_array($modifiers) ? $modifiers : ($modifiers ? [$modifiers] : []);

            if (!in_array('none', $modifiers) && !empty($modifiers)) {
                if (in_array('nullable', $modifiers)) $options['nullable'] = true;
                if (in_array('unique', $modifiers)) $options['unique'] = true;
                if (in_array('default', $modifiers)) {
                    $options['default'] = $this->command->ask("Enter default value for '{$columnName}':");
                }
            }

            if ($type === 'enum') {
                $enumValues = $this->command->ask("Enter allowed values for '{$columnName}' (comma-separated, e.g., active,inactive):");
                if (empty(trim($enumValues))) {
                    $this->command->error("Enum values for '{$columnName}' cannot be empty. Skipping.");
                    continue;
                }
                $options['enum_values'] = explode(',', trim($enumValues));
            }

            $attributes[$columnName] = $options;
        }
    }

    protected function updateModelFile(string $path, string $name, array $fillable)
    {
        $content = File::get($path);
        $fillableStr = implode("',\n        '", $fillable);
        $fillableProperty = empty($fillable) ? '' : "    protected \$fillable = [\n        '$fillableStr'\n    ];";
        $relationships = $this->generateRelationships($name);

        $content = preg_replace(
            '/(class\s+' . $name . '\s+extends\s+Model\s*{)/',
            "$1\n$fillableProperty\n$relationships",
            $content
        );

        File::put($path, $content);
        $this->command->info("Model '{$name}' created successfully!");
    }

    protected function generateRelationships(string $name): string
    {
        $relationships = '';
        foreach ($this->attributes as $column => $options) {
            if ($options['type'] === 'foreignId') {
                $relatedModel = Str::studly(str_replace('_id', '', $column));
                $methodName = Str::camel(str_replace('_id', '', $column));
                $relationships .= "\n    public function {$methodName}()\n    {\n        return \$this->belongsTo({$relatedModel}::class);\n    }\n";
            }
        }
        return $relationships;
    }
}