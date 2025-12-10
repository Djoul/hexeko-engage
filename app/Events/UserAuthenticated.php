<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserAuthenticated
{
    use Dispatchable, SerializesModels;

    public readonly string $timestamp;

    public function __construct(
        public readonly User $user,
        ?string $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? now()->toIso8601String();
    }
}
