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
        $migrationPath = $this->createMigration($name);
        $this->updateMigrationFile($migrationPath, $name, $attributes);
    }

    protected function createMigration(string $name): string
    {
        $tableName = Str::snake(Str::plural($name));
        $this->command->call('make:migration', [
            'name' => "create_{$tableName}_table",
            '--create' => $tableName,
        ]);

        return collect(File::files(database_path('migrations')))
            ->filter(fn ($file) => str_contains($file->getFilename(), "create_{$tableName}_table"))
            ->first()
            ->getPathname();
    }

    protected function updateMigrationFile(string $path, string $name, array $attributes)
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
        $this->command->info("Migration for {$name} created successfully!");
    }
}