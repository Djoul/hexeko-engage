<?php

declare(strict_types=1);

namespace App\Documentation\ThirdPartyApis;

use ReflectionClass;
use ReflectionMethod;
use RuntimeException;

abstract class BaseApiDoc
{
    abstract public static function getApiVersion(): string;

    abstract public static function getLastVerified(): string;

    abstract public static function getProviderName(): string;

    /**
     * @return array<string, mixed>
     */
    protected static function loadResponse(string $file): array
    {
        $provider = static::getProviderName();
        $path = app_path("Documentation/ThirdPartyApis/responses/{$provider}/{$file}");

        if (! file_exists($path)) {
            throw new RuntimeException("Response file not found: {$file}");
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException("Failed to read file: {$file}");
        }

        $decoded = json_decode($contents, true);
        if (! is_array($decoded)) {
            throw new RuntimeException("Invalid JSON in file: {$file}");
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }

    /**
     * @return array<int, string>
     */
    final public static function getAllEndpoints(): array
    {
        $reflection = new ReflectionClass(static::class);
        $methods = $reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC);

        $endpoints = [];
        $excludedMethods = [
            'getApiVersion',
            'getLastVerified',
            'getProviderName',
            'loadResponse',
            'getAllEndpoints',
        ];

        foreach ($methods as $method) {
            if (! in_array($method->getName(), $excludedMethods)) {
                $endpoints[] = $method->getName();
            }
        }

        return $endpoints;
    }
}
