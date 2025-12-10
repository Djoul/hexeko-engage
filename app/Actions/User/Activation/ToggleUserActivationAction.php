<?php

declare(strict_types=1);

namespace App\Actions\User\Activation;

use App\Models\User;
use App\Services\Models\UserService;
use Illuminate\Support\Facades\DB;

class ToggleUserActivationAction
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Toggle user activation status for a specific financer
     *
     * @return array{active: bool}
     */
    public function execute(User $user, string $financerId): array
    {
        return DB::transaction(function () use ($user, $financerId): array {
            // Toggle the activation status and get the new status
            $newStatus = $this->userService->toggleActivationForFinancer($user, $financerId);

            return ['active' => $newStatus];
        });
    }
}
