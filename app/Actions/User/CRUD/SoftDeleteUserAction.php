<?php

declare(strict_types=1);

namespace App\Actions\User\CRUD;

use App\Models\User;
use App\Services\Models\UserService;
use Illuminate\Support\Facades\DB;

class SoftDeleteUserAction
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Soft delete a user
     */
    public function execute(User $user): bool
    {
        return DB::transaction(function () use ($user): bool {
            // Perform soft delete
            return $this->userService->delete($user);
        });
    }
}
