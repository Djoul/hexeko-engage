<?php

namespace App\Integrations\InternalCommunication\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleClosedWithoutInteraction
{
    use Dispatchable, SerializesModels;

    public string $userId;

    public int|string $articleId;

    public function __construct(string $userId, int|string $articleId)
    {
        $this->userId = $userId;
        $this->articleId = $articleId;
    }

    public function getTarget(): string
    {
        return "article:{$this->articleId}";
    }
}
