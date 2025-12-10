<?php

namespace App\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ArticleViewed
{
    use Dispatchable, SerializesModels;

    public string $userId;

    public string $articleId;

    public function __construct(string $userId, string $articleId)
    {
        $this->userId = $userId;
        $this->articleId = $articleId;
    }
}
