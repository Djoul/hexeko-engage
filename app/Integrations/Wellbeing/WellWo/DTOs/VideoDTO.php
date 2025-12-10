<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\DTOs;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
class VideoDTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $image = null,
        public readonly ?string $video = null,
        public readonly ?string $length = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: array_key_exists('id', $data) && is_string($data['id']) ? $data['id'] : '',
            name: array_key_exists('name', $data) && is_string($data['name']) ? $data['name'] : '',
            image: array_key_exists('image', $data) && is_string($data['image']) ? $data['image'] : null,
            video: array_key_exists('video', $data) && is_string($data['video']) ? $data['video'] : null,
            length: array_key_exists('length', $data) && is_string($data['length']) ? $data['length'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'video' => $this->video,
            'length' => $this->length,
        ];
    }
}
