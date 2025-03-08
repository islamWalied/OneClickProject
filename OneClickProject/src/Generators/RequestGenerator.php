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
        $requestDirectory = app_path('Http/Requests');

        if (!File::exists($requestDirectory)) {
            File::makeDirectory($requestDirectory, 0755, true);
        }

        $storeRequestPath = "{$requestDirectory}/Store{$name}Request.php";
        $storeRequestContent = $this->generateStoreRequestContent($name, $attributes);
        File::put($storeRequestPath, $storeRequestContent);

        $updateRequestPath = "{$requestDirectory}/Update{$name}Request.php";
        $updateRequestContent = $this->generateUpdateRequestContent($name, $attributes);
        File::put($updateRequestPath, $updateRequestContent);

        $this->command->info("Request classes for {$name} created successfully!");
    }

    protected function generateStoreRequestContent(string $name, array $attributes): string
    {
        $rules = [];
        $processedAttributes = [];

        foreach ($attributes as $attribute => $type) {
            if (in_array($attribute, $processedAttributes)) {
                continue;
            }

            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                $rules[] = "'$attribute' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120'";
                $processedAttributes[] = $attribute;
                continue;
            }

            $rule = match ($type) {
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

    protected function generateUpdateRequestContent(string $name, array $attributes): string
    {
        $rules = [];
        $processedAttributes = [];

        foreach ($attributes as $attribute => $type) {
            if (in_array($attribute, $processedAttributes)) {
                continue;
            }

            if (in_array($attribute, ['image', 'photo', 'plan_image'])) {
                $rules[] = "'$attribute' => 'sometimes|image|mimes:jpeg,png,jpg,gif,svg|max:10240'";
                $processedAttributes[] = $attribute;
                continue;
            }

            $rule = match ($type) {
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

    protected function generateForeignKeyValidationRule(string $attribute): string
    {
        $tableName = Str::plural(str_replace('_id', '', $attribute));
        return "'$attribute' => 'required|numeric|exists:{$tableName},id'";
    }

    protected function generateForeignKeyUpdateValidationRule(string $attribute): string
    {
        $tableName = Str::plural(str_replace('_id', '', $attribute));
        return "'$attribute' => 'sometimes|numeric|exists:{$tableName},id'";
    }
}