<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\DTOs;

use Illuminate\Contracts\Support\Arrayable;

class WellWoDTO implements Arrayable
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $image = null,
        public readonly ?string $description = null,
        public readonly ?int $videosCount = null
    ) {}

    public static function fromApiResponse(array $data): self
    {
        return new self(
            id: $data['id'] ?? '',
            name: $data['name'] ?? '',
            image: $data['image'] ?? null,
            description: $data['description'] ?? null,
            videosCount: $data['videos_count'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'description' => $this->description,
            'videos_count' => $this->videosCount,
        ];
    }
}
