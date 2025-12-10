<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\TranslationMigration;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TranslationMigrationApplied
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly TranslationMigration $migration,
        public readonly ?string $backupPath = null,
    ) {}
}
