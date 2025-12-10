<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\DTOs;

class ToggleArticleFavoriteDTO
{
    public function __construct(
        public string $userId,
        public string $articleId,
        public ?string $articleTranslationId = null,
    ) {}
}
