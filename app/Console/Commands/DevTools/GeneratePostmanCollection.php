<?php

namespace App\Console\Commands\DevTools;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GeneratePostmanCollection extends Command
{
    protected $signature = 'postman:generate {model} {--integration=}';

    protected $description = 'Generate a Postman collection with a folder for a given model using Model Factory';

    public function handle(): void
    {
        $model = ucfirst($this->argument('model'));
        $integration = $this->option('integration');

        if (empty($integration)) {
            $factoryClass = "Database\Factories\\{$model}Factory";
            $apiVersionPath = config('app.api.version_path');
            if (! is_string($apiVersionPath)) {
                $apiVersionPath = 'v1'; // Default value if config is not a string
            }
            $baseUrl = '{{base_url}}/api/'.$apiVersionPath.'/'.strtolower($model).'s';
        } else {
            $factoryClass = "App\Integrations\\{$integration}\Database\\factories\\{$model}Factory";
            $apiVersionPath = config('app.api.version_path');
            if (! is_string($apiVersionPath)) {
                $apiVersionPath = 'v1'; // Default value if config is not a string
            }
            $baseUrl = '{{base_url}}/api/'.$apiVersionPath.'/int/'.strtolower($integration).'/'.strtolower($model).'s';

        }

        $requestData = $this->generateFakeData($factoryClass);

        $requests = [
            [
                'name' => "Get All $model",
                'request' => [
                    'method' => 'GET',
                    'url' => ['raw' => "$baseUrl", 'host' => [$baseUrl]],
                ],
            ],
            [
                'name' => "Get Single $model",
                'request' => [
                    'method' => 'GET',
                    'url' => [
                        'raw' => "$baseUrl/{id}",
                        'host' => ["$baseUrl/{id}"],
                        'variable' => [['key' => 'id', 'value' => '1']],
                    ],
                ],
            ],
            [
                'name' => "Create $model",
                'request' => [
                    'method' => 'POST',
                    'url' => ['raw' => "$baseUrl", 'host' => [$baseUrl]],
                    'body' => [
                        'mode' => 'raw',
                        'raw' => json_encode($requestData, JSON_PRETTY_PRINT),
                    ],
                    'header' => [['key' => 'Content-Type', 'value' => 'application/json']],
                ],
            ],
            [
                'name' => "Update $model",
                'request' => [
                    'method' => 'PUT',
                    'url' => [
                        'raw' => "$baseUrl/{id}",
                        'host' => ["$baseUrl/{id}"],
                    ],
                    'body' => [
                        'mode' => 'raw',
                        'raw' => json_encode($requestData, JSON_PRETTY_PRINT),
                    ],
                    'header' => [['key' => 'Content-Type', 'value' => 'application/json']],
                ],
            ],
            [
                'name' => "Delete $model",
                'request' => [
                    'method' => 'DELETE',
                    'url' => [
                        'raw' => "$baseUrl/{id}",
                        'host' => ["$baseUrl/{id}"],
                    ],
                ],
            ],
        ];

        $collection = [
            'info' => [
                'name' => 'UpEngage New Collection CRUD',
                'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
            ],
            'item' => [
                [
                    'name' => "$model",
                    'item' => $requests,
                ],
            ],
        ];

        $filePath = storage_path("postman/{$model}_collection.json");
        File::ensureDirectoryExists(storage_path('postman') ?: '');
        File::put($filePath, json_encode($collection, JSON_PRETTY_PRINT) ?: '');

        $this->info("Postman collection with folder and dynamic data generated: $filePath");
    }

    /**
     * Génère des valeurs dynamiques en utilisant le Model Factory.
     * afin de les utiliser dans les requêtes Create && Update.
     *
     * @return array<string,mixed>
     */
    private function generateFakeData(string $factoryClass)
    {
        if (! class_exists($factoryClass)) {
            $this->error('Model Factory class not found');
        }

        $factoryInstance = app($factoryClass);

        if (! method_exists($factoryInstance, 'definition')) {
            $this->error('Factory does not have a definition method');
        }

        return $factoryInstance->definition();
    }
}
