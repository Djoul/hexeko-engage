<?php

namespace App\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAccountActivated
{
    use Dispatchable, SerializesModels;

    public string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }
}
