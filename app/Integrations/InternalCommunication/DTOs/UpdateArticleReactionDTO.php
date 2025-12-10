<?php

declare(strict_types=1);

namespace App\Integrations\InternalCommunication\DTOs;

class UpdateArticleReactionDTO
{
    public function __construct(
        public string $userId,
        public string $articleId,
        public ?string $reaction,
        public ?string $articleTranslationId = null,
    ) {}
}
