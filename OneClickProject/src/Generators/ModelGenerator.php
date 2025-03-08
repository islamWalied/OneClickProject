<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ModelGenerator
{
    protected Command $command;
    protected array $attributes = []; // Initialize as an empty array
    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array &$attributes): bool
    {
        if (File::exists(app_path("Models/{$name}.php"))) {
            $this->command->error("Model {$name} already exists!");
            return false;
        }

        $this->command->call('make:model', ['name' => $name]);
        $this->attributes = $attributes; // Store the attributes in the class property
        $this->collectModelAttributes($attributes);
        $this->updateModelFile($name, array_keys($attributes));

        return true;
    }

    protected function collectModelAttributes(array &$attributes)
    {
        while (true) {
            $columnName = $this->command->ask('Enter column name (or "done" to finish):');

            if (strtolower($columnName) === 'done') {
                break;
            }
            if (empty($columnName)) {
                $this->command->error('Column name cannot be empty.');
                continue;
            }

            $attributes[$columnName] = $this->command->choice('Select column type:', [
                'string', 'integer', 'text', 'boolean', 'date', 'datetime',
                'timestamp', 'float', 'decimal', 'foreignId',
            ]);
        }
    }

    protected function updateModelFile(string $name, array $fillable)
    {
        $modelPath = app_path("Models/{$name}.php");
        $content = File::get($modelPath);

        $fillableStr = implode("',\n        '", $fillable);
        $fillableProperty = "    protected \$fillable = [\n        '$fillableStr'\n    ];";

        $relationships = $this->generateRelationships($name);

        $content = preg_replace(
            '/(class\s+' . $name . '\s+extends\s+Model\s*{)/',
            "$1\n$fillableProperty\n$relationships",
            $content
        );

        File::put($modelPath, $content);
        $this->command->info("Model {$name} created successfully!");
    }

    protected function generateRelationships(string $name): string
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
}