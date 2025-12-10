<?php

namespace App\Integrations\InternalCommunication\Events\Metrics;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 *@deprecated
 */
class CommunicationSectionVisited
{
    use Dispatchable, SerializesModels;

    public string $userId;

    public function __construct(string $userId)
    {
        $this->userId = $userId;
    }

    public function getTarget(): string
    {
        return 'internal-communication';
    }
}
