<?php

namespace App\Actions\User\CRUD;

use App\Models\User;
use App\Services\Models\UserService;
use InvalidArgumentException;

class DeleteUserAction
{
    public function __construct(protected UserService $userService) {}

    /**
     * Deactivate user for the current financer
     */
    public function handle(User $user): bool
    {
        // Get the active financer ID from the request
        $financerId = activeFinancerID();

        if (! is_string($financerId) || $financerId === '' || $financerId === '0') {
            throw new InvalidArgumentException('Financer context is required');
        }

        // Deactivate the user for this financer
        return $this->userService->deactivateForFinancer($user, $financerId);
    }
}
