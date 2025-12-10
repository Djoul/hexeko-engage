<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class ClassVideoDTO implements Arrayable
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description = null,
        public readonly ?string $url = null,
        public readonly ?string $level = null,
        public readonly ?string $image = null
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {

        return new self(
            name: array_key_exists('name', $data) && is_string($data['name']) ? $data['name'] : '',
            description: array_key_exists('description', $data) && is_string($data['description']) ? $data['description'] : null,
            url: array_key_exists('url', $data) && is_string($data['url']) ? $data['url'] : null,
            level: array_key_exists('level', $data) && is_string($data['level']) ? $data['level'] : null,
            image: array_key_exists('image', $data) && is_string($data['image']) ? $data['image'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'url' => $this->url,
            'level' => $this->level,
            'image' => $this->image,
        ];
    }
}
