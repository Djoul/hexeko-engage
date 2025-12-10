<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\DTOs;

use JsonException;

class ContentAvailabilityDTO
{
    public string $version = '1.0.0';

    public ?string $analyzedAt = null;

    public ?string $language = null;

    public array $endpoints = [];

    public array $statistics = [];

    public static function fromJson(string $json): self
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException("Failed to parse ContentAvailability JSON: {$e->getMessage()}", 0, $e);
        }

        $dto = new self;
        $dto->version = $data['version'] ?? '1.0.0';
        $dto->analyzedAt = $data['analyzedAt'] ?? null;
        $dto->language = $data['language'] ?? null;
        $dto->endpoints = $data['endpoints'] ?? [];
        $dto->statistics = $data['statistics'] ?? [];

        return $dto;
    }

    public function toJson(): string
    {
        $data = [
            'version' => $this->version,
            'analyzedAt' => $this->analyzedAt,
            'language' => $this->language,
            'endpoints' => $this->endpoints,
            'statistics' => $this->statistics,
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    public function hasDataForEndpoint(string $endpoint): bool
    {
        return isset($this->endpoints[$endpoint]) && ! empty($this->endpoints[$endpoint]);
    }

    public function getAvailableIds(string $endpoint): array
    {
        return $this->endpoints[$endpoint] ?? [];
    }
}
