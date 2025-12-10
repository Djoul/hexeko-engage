<?php

declare(strict_types=1);

namespace App\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use InvalidArgumentException;
use JsonException;
use JsonSerializable;

/**
 * @implements Arrayable<string, mixed>
 */
abstract class BaseDTO implements Arrayable, JsonSerializable
{
    /**
     * Create a new DTO instance from an array
     *
     * @param  array<string, mixed>  $data
     */
    final public static function from(array $data): static
    {
        $className = static::class;
        /** @var static $instance */
        $instance = new $className;

        foreach ($data as $key => $value) {
            if (property_exists($instance, $key)) {
                $instance->{$key} = $value;
            }
        }

        return $instance;
    }

    /**
     * Create a new DTO instance from a request
     */
    final public static function fromRequest(Request $request): static
    {
        $validatedData = [];
        if (method_exists($request, 'validated')) {
            /** @var array<string, mixed> $validatedData */
            $validatedData = $request->validated();
        }

        return self::from($validatedData);
    }

    /**
     * Convert the DTO to an array
     *
     * @return array<string, mixed>
     *
     * @phpstan-ignore-next-line
     */
    public function toArray(): array
    {
        $properties = get_object_vars($this);

        return Arr::where($properties, function ($value): bool {
            return $value !== null;
        });
    }

    /**
     * Convert the DTO to JSON
     */
    final public function toJson(int $options = 0): string
    {
        $json = json_encode($this->jsonSerialize(), $options);
        if ($json === false) {
            throw new JsonException('Failed to encode DTO to JSON');
        }

        return $json;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array<string, mixed>
     */
    final public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Get a specific property
     */
    final public function get(string $key, mixed $default = null): mixed
    {
        return $this->{$key} ?? $default;
    }

    /**
     * Check if a property exists and is not null
     */
    final public function has(string $key): bool
    {
        return property_exists($this, $key) && $this->{$key} !== null;
    }

    /**
     * Dynamically access properties
     */
    final public function __get(string $name): mixed
    {
        if (! property_exists($this, $name)) {
            throw new InvalidArgumentException("Property {$name} does not exist on ".static::class);
        }

        return $this->{$name};
    }

    /**
     * Dynamically set properties
     */
    final public function __set(string $name, mixed $value): void
    {
        if (! property_exists($this, $name)) {
            throw new InvalidArgumentException("Property {$name} does not exist on ".static::class);
        }

        $this->{$name} = $value;
    }
}
