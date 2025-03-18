<?php

namespace IslamWalied\OneClickProject\Generators;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RequestGenerator
{
    protected Command $command;

    public function __construct(Command $command)
    {
        $this->command = $command;
    }

    public function generate(string $name, array $attributes)
    {
        if (!$this->isValidName($name)) {
            $this->command->error("Invalid request name '{$name}'. Use alphanumeric characters and start with a letter.");
            return;
        }

        $requestDirectory = app_path('Http/Requests');
        if (!File::exists($requestDirectory)) {
            File::makeDirectory($requestDirectory, 0755, true);
        }

        $storePath = "{$requestDirectory}/Store{$name}Request.php";
        $updatePath = "{$requestDirectory}/Update{$name}Request.php";

        if (File::exists($storePath) || File::exists($updatePath)) {
            $this->command->error("Request files for '{$name}' already exist!");
            return;
        }

        $storeContent = $this->generateStoreRequestContent($name, $attributes);
        $updateContent = $this->generateUpdateRequestContent($name, $attributes);

        File::put($storePath, $storeContent);
        File::put($updatePath, $updateContent);
        $this->command->info("Request classes for '{$name}' created successfully!");
    }

    protected function isValidName(string $name): bool
    {
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]+$/', $name) === 1;
    }

    protected function generateStoreRequestContent(string $name, array $attributes): string
    {
        if (empty($attributes)) {
            $this->command->warn("No attributes provided for '{$name}' store request. Rules will be empty.");
        }

        $rules = collect($attributes)->map(function ($options, $attribute) {
            if (!$this->isValidName($attribute)) {
                $this->command->error("Invalid attribute name '{$attribute}' in store request. Skipping.");
                return null;
            }

            $type = $options['type'] ?? 'string';
            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                return "'$attribute' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:10240'";
            }

            return match ($type) {
                'string' => "'$attribute' => 'required|string|max:255'",
                'integer' => "'$attribute' => 'required|integer'",
                'boolean' => "'$attribute' => 'required|boolean'",
                'date', 'datetime', 'timestamp' => "'$attribute' => 'required|date'",
                'foreignId' => "'$attribute' => 'required|numeric|exists:" . Str::plural(str_replace('_id', '', $attribute)) . ",id'",
                default => "'$attribute' => 'required'"
            };
        })->filter()->implode(",\n            ");

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
            {$rules}
        ];
    }
}
EOF;
    }

    protected function generateUpdateRequestContent(string $name, array $attributes): string
    {
        if (empty($attributes)) {
            $this->command->warn("No attributes provided for '{$name}' update request. Rules will be empty.");
        }

        $rules = collect($attributes)->map(function ($options, $attribute) {
            if (!$this->isValidName($attribute)) {
                $this->command->error("Invalid attribute name '{$attribute}' in update request. Skipping.");
                return null;
            }

            $type = $options['type'] ?? 'string';
            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                return "'$attribute' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:10240'";
            }

            return match ($type) {
                'string' => "'$attribute' => 'sometimes|string|max:255'",
                'integer' => "'$attribute' => 'sometimes|integer'",
                'boolean' => "'$attribute' => 'sometimes|boolean'",
                'date', 'datetime', 'timestamp' => "'$attribute' => 'sometimes|date'",
                'foreignId' => "'$attribute' => 'sometimes|numeric|exists:" . Str::plural(str_replace('_id', '', $attribute)) . ",id'",
                default => "'$attribute' => 'sometimes'"
            };
        })->filter()->implode(",\n            ");

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
            {$rules}
        ];
    }
}
EOF;
    }
}