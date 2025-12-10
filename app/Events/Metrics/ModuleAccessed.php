<?php

namespace App\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ModuleAccessed
{
    use Dispatchable, SerializesModels;

    public string $userId;

    public string $module;

    public function __construct(string $userId, string $module)
    {
        $this->userId = $userId;
        $this->module = $module;
    }
}
