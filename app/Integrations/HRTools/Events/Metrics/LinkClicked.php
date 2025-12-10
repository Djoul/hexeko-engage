<?php

namespace App\Integrations\HRTools\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LinkClicked
{
    use Dispatchable, SerializesModels;

    public function __construct(public string $userId, public string $link) {}

    public function getTarget(): string
    {
        return "link:{$this->link}";
    }
}
