<?php

namespace App\Events\Vault;

use App\DTOs\Vault\VaultSessionDTO;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class VaultSessionCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public User $user,
        public Financer $financer,
        public VaultSessionDTO $session
    ) {}
}
