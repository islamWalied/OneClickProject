<?php

namespace IslamWalied\OneClickProject\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;

class ExportPostmanCollection extends Command
{
    protected $signature = 'postman:export {name? : The name of the collection}';
    protected $description = 'Export API routes as a Postman collection with resource-based folders, custom names, and dummy data from request validation';

    public function handle()
    {
        $name = $this->argument('name') ?? config('postman.collection_name', 'Laravel API');
        $bearerToken = config('postman.authentication.token', '{{api_token}}');
        $collection = $this->generateCollection($name, $bearerToken);
        $json = json_encode($collection, JSON_PRETTY_PRINT);

        $fileName = config('postman.export_directory', storage_path('app')) . '/postman_collection_' . time() . '.json';
        file_put_contents($fileName, $json);

        $this->info("Postman collection exported to: {$fileName}");
    }

    protected function generateCollection($name, $bearerToken)
    {
        $routes = Route::getRoutes();
        $folders = [];

        foreach ($routes as $route) {
            if ($this->shouldIncludeRoute($route)) {
                $uri = $route->uri();
                $pathSegments = explode('/', trim($uri, '/'));

                if (count($pathSegments) >= 3 && $pathSegments[0] === 'api' && $pathSegments[1] === 'v1') {
                    $resourceName = strtolower($pathSegments[2]);

                    if (!isset($folders[$resourceName])) {
                        $folders[$resourceName] = [
                            'name' => $resourceName,
                            'item' => [],
                        ];
                    }

                    $folders[$resourceName]['item'][] = $this->routeToPostmanItem($route);
                } else {
                    $resourceName = 'General';
                    if (!isset($folders[$resourceName])) {
                        $folders[$resourceName] = [
                            'name' => $resourceName,
                            'item' => [],
                        ];
                    }
                    $folders[$resourceName]['item'][] = $this->routeToPostmanItem($route);
                }
            }
        }

        return [
            'info' => [
                'name' => $name,
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'auth' => [
                'type' => 'bearer',
                'bearer' => [
                    'token' => $bearerToken,
                ],
            ],
            'item' => array_values($folders),
        ];
    }

    protected function shouldIncludeRoute($route)
    {
        $middleware = $route->middleware();
        $includeMiddleware = config('postman.include_middleware', ['api']);
        return in_array('api', $middleware) && !in_array('_ignition', $route->middleware());
    }

    protected function routeToPostmanItem($route)
    {
        $uri = $route->uri();
        $methods = $route->methods();
        $method = in_array('GET', $methods) ? 'GET' : $methods[0];

        // Determine the name based on method and URI pattern
        $hasParameter = strpos($uri, '{') !== false;
        $baseName = $route->getName() ?? $uri;

        switch ($method) {
            case 'GET':
                $name = $hasParameter ? 'get one' : 'get all';
                break;
            case 'POST':
                $name = 'store';
                break;
            case 'PATCH':
                $name = 'update';
                break;
            case 'DELETE':
                $name = 'delete';
                break;
            default:
                $name = $baseName;
        }

        // Generate dummy data based on method and request validation
        $resource = strtolower(explode('/', trim($uri, '/'))[2] ?? 'resource');
        $dummyData = $this->generateDummyData($method, $hasParameter, $resource, $route);

        return [
            'name' => $name,
            'request' => [
                'method' => $method,
                'header' => [
                    ['key' => 'Accept', 'value' => 'application/json'],
                    ['key' => 'Content-Type', 'value' => 'multipart/form-data'],
                ],
                'url' => [
                    'raw' => '{{base_url}}/' . $uri,
                    'host' => '{{base_url}}',
                    'path' => explode('/', $uri),
                ],
                'body' => $dummyData['body'] ?? null,
                'url' => array_merge(
                    $dummyData['query'] ? ['query' => $dummyData['query']] : [],
                    ['raw' => '{{base_url}}/' . $uri, 'host' => '{{base_url}}', 'path' => explode('/', $uri)]
                ),
            ],
            'response' => [],
        ];
    }

    protected function generateDummyData($method, $hasParameter, $resource, $route)
    {
        $dummyData = ['body' => null, 'query' => null];
        $attributes = $this->getValidationAttributes($route);

        switch ($method) {
            case 'GET':
                if (!$hasParameter) {
                    $dummyData['query'] = [
                        ['key' => 'per_page', 'value' => '10', 'description' => 'Items per page'],
                        ['key' => 'page', 'value' => '2', 'description' => 'Page number'],
                    ];
                }
                break;

            case 'POST':
                if (!empty($attributes)) {
                    $formData = [];
                    foreach ($attributes as $attribute) {
                        $formData[] = [
                            'key' => $attribute,
                            'value' => $this->generateDummyValue($attribute),
                            'type' => 'text',
                            'description' => "Sample value for {$attribute}",
                        ];
                    }
                    $dummyData['body'] = [
                        'mode' => 'formdata',
                        'formdata' => $formData,
                    ];
                }
                break;

            case 'PATCH':
                if (!empty($attributes)) {
                    $formData = [];
                    foreach ($attributes as $attribute) {
                        $formData[] = [
                            'key' => $attribute,
                            'value' => $this->generateDummyValue($attribute, 'updated'),
                            'type' => 'text',
                            'description' => "Updated value for {$attribute}",
                        ];
                    }
                    $dummyData['body'] = [
                        'mode' => 'formdata',
                        'formdata' => $formData,
                    ];
                }
                break;

            case 'DELETE':
                $dummyData['query'] = [['key' => 'id', 'value' => '1', 'description' => 'Sample ID to delete']];
                break;
        }

        return $dummyData;
    }

    protected function getValidationAttributes($route)
    {
        $attributes = [];

        try {
            $action = $route->getAction()['uses'];
            if (is_string($action)) {
                list($controller, $method) = explode('@', $action);

                $reflection = new ReflectionClass($controller);
                $methodReflection = $reflection->getMethod($method);

                foreach ($methodReflection->getParameters() as $param) {
                    $paramClass = $param->getType()?->getName();
                    if ($paramClass && class_exists($paramClass) && is_subclass_of($paramClass, \Illuminate\Foundation\Http\FormRequest::class)) {
                        $requestReflection = new ReflectionClass($paramClass);
                        $rulesMethod = $requestReflection->getMethod('rules');

                        // Create a mock request instance to avoid validation
                        $requestInstance = $requestReflection->newInstanceWithoutConstructor();
                        $rules = $rulesMethod->invoke($requestInstance);

                        if (is_array($rules)) {
                            $attributes = array_keys($rules);
                        }
                        break;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->warn("Could not extract validation rules for route: {$route->uri()} - {$e->getMessage()}");
        }

        return $attributes;
    }

    protected function generateDummyValue($attribute, $prefix = 'sample')
    {
        $value = "$prefix " . strtolower(str_replace('_', ' ', $attribute));
        if (preg_match('/(id|count|number)/i', $attribute)) {
            return (string) rand(1, 100);
        } elseif (preg_match('/(name|title|description)/i', $attribute)) {
            return $value;
        } elseif (preg_match('/(email)/i', $attribute)) {
            return "{$prefix}.email@example.com";
        } elseif (preg_match('/(code|slug)/i', $attribute)) {
            return strtoupper(substr($value, 0, 3)) . rand(1, 999);
        }
        return $value;
    }
}
