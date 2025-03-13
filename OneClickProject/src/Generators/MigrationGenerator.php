<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array $attributes)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid model name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        $tableName = Str::snake(Str::plural($name));
        $migrationPath = $this->handleExistingTable($tableName, $name);

        if ($migrationPath === null) {
            $this->command->info("Migration generation for '{$name}' skipped.");
            return;
        }

        $this->updateMigrationFile($migrationPath, $name, $attributes);
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $name) === 1;
    }

    protected function handleExistingTable(string $tableName, string $originalName): ?string
    {
        $migrationFiles = File::files(database_path('migrations'));
        $existingMigration = collect($migrationFiles)->first(fn ($file) =>
            str_contains($file->getFilename(), "create_{$tableName}_table") ||
            str_contains($file->getFilename(), "recreate_{$tableName}_table")
        );

        if ($existingMigration) {
            $this->command->warn("A migration for table '{$tableName}' already exists!");
            $choice = $this->command->choice(
                "What would you like to do?",
                ['overwrite' => 'Overwrite the existing migration', 'rename' => 'Create a new migration with a different name', 'skip' => 'Skip migration generation'],
                'skip'
            );

            switch ($choice) {
                case 'overwrite':
                    File::delete($existingMigration->getPathname());
                    return $this->createMigration($originalName, true);
                case 'rename':
                    $newName = $this->promptForValidName("Enter a new model name (singular, e.g., 'Post')", $originalName . '_new');
                    return $this->createMigration($newName);
                case 'skip':
                    return null;
            }
        }

        return $this->createMigration($originalName);
    }

    protected function promptForValidName(string $prompt, string $default = null): string
    {
        while (true) {
            $name = $this->command->ask($prompt, $default);
            if ($this->isValidName($name)) {
                return $name;
            }
            $this->command->error("Invalid name '{$name}'. Use alphanumeric characters and start with a letter.");
        }
    }

    protected function createMigration(string $name, bool $forceOverwrite = false): string
    {
        $tableName = Str::snake(Str::plural($name));
        $migrationName = $forceOverwrite ? "recreate_{$tableName}_table" : "create_{$tableName}_table";
        $timestamp = now()->format('Y_m_d_His');
        $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");

        if (File::exists($migrationPath)) {
            $this->command->error("Migration file '{$migrationPath}' already exists! Adjusting timestamp.");
            $timestamp .= '_'.rand(100, 999); // Avoid collision
            $migrationPath = database_path("migrations/{$timestamp}_{$migrationName}.php");
        }

        $stub = $this->getMigrationStub($tableName);
        File::put($migrationPath, $stub);

        return $migrationPath;
    }

    protected function getMigrationStub(string $tableName): string
    {
        return <<<EOF
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            \$table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
EOF;
    }

    protected function updateMigrationFile(string $path, string $name, array $attributes)
    {
        if (empty($attributes)) {
            $this->command->warn("No attributes provided for '{$name}'. Migration includes only ID and timestamps.");
        }

        foreach ($attributes as $column => $options) {
            if (!$this->isValidName($column)) {
                $this->command->error("Invalid column name '{$column}'. Skipping.");
                unset($attributes[$column]);
                continue;
            }
            if (!isset($options['type']) || !in_array($options['type'], ['string', 'integer', 'text', 'boolean', 'date', 'datetime', 'timestamp', 'float', 'decimal', 'foreignId', 'enum'])) {
                $this->command->error("Invalid or missing type for column '{$column}'. Skipping.");
                unset($attributes[$column]);
            }
        }

        $schema = collect($attributes)->map(function ($options, $name) {
            $type = $options['type'];
            $nullable = $options['nullable'] ?? false;
            $default = $options['default'] ?? null;
            $unique = $options['unique'] ?? false;
            $enumValues = $options['enum_values'] ?? [];

            if ($type === 'foreignId') {
                $column = "\$table->foreignId('$name')->constrained()->cascadeOnDelete()->cascadeOnUpdate()";
            } elseif ($type === 'enum') {
                if (empty($enumValues)) {
                    $this->command->error("Enum column '$name' requires values. Using string instead.");
                    return "\$table->string('$name');";
                }
                $valuesStr = "['" . implode("', '", $enumValues) . "']";
                $column = "\$table->enum('$name', $valuesStr)";
            } else {
                $column = "\$table->$type('$name')";
            }

            if ($nullable) $column .= "->nullable()";
            if ($default !== null) $column .= "->default(" . (is_numeric($default) ? $default : "'$default'") . ")";
            if ($unique) $column .= "->unique()";

            return $column . ";";
        })->implode("\n            ");

        $content = File::get($path);

        if (str_contains($path, 'recreate_')) {
            $tableName = Str::snake(Str::plural($name));
            $content = preg_replace(
                '/public function up\(\): void\s*{[^}]*Schema::create\(\'([^\']+)\', function \(Blueprint \$table\) {[^}]*}\);[^}]*}/s',
                "public function up(): void\n    {\n        Schema::dropIfExists('$tableName');\n        Schema::create('$tableName', function (Blueprint \$table) {\n            \$table->id();\n            $schema\n            \$table->timestamps();\n        });\n    }",
                $content
            );
        } else {
            $content = preg_replace(
                '/(\$table->id\(\);)/',
                "$1\n            $schema",
                $content
            );
        }

        File::put($path, $content);
        $this->command->info("Migration for '{$name}' created successfully!");
    }
}