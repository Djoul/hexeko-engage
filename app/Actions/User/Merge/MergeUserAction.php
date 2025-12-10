<?php

declare(strict_types=1);

namespace App\Actions\User\Merge;

use App\Models\User;
use App\Services\Models\UserService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * Action to merge an invited user into an existing user account
 *
 * This action handles the complex process of:
 * 1. Finding the invited user and target user
 * 2. Transferring financer relationships from invited to existing user
 * 3. Activating the transferred financer relationships
 * 4. Soft deleting the invited user
 *
 * All operations are wrapped in a database transaction for data integrity.
 */
class MergeUserAction
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Execute the merge user action
     *
     * @param  string  $invitedUserId  UUID of invited user to merge
     * @param  string  $targetUserEmail  Email of existing user to merge into
     * @return User The updated existing user with merged financers
     *
     * @throws ModelNotFoundException If invited user or target user not found
     */
    public function execute(string $invitedUserId, string $targetUserEmail): User
    {
        return DB::transaction(function () use ($invitedUserId, $targetUserEmail): User {
            // Find the invited user with financers relationship
            $invitedUser = User::with('financers')
                ->where('id', $invitedUserId)
                ->where('invitation_status', 'pending')
                ->first();

            if (! $invitedUser instanceof User) {
                throw new ModelNotFoundException('Invited user not found');
            }

            // Find the existing user by email
            $existingUser = User::where('email', $targetUserEmail)
                ->where(function ($query): void {
                    $query->whereNull('invitation_status')
                        ->orWhere('invitation_status', '!=', 'pending');
                })
                ->first();

            if (! $existingUser instanceof User) {
                throw new ModelNotFoundException("User not found with email: {$targetUserEmail}");
            }

            // Prepare financer data from invited user
            // Set all transferred financers to active status
            $financerData = $invitedUser->financers->map(function ($financer): array {
                return [
                    'id' => $financer->id,
                    'pivot' => [
                        'active' => true, // Activate transferred financers
                        'from' => $financer->pivot->from ?? now(),
                        'sirh_id' => $financer->pivot->sirh_id ?? '',
                    ],
                ];
            })->toArray();

            // Sync financers from invited user to existing user
            // This will preserve existing financers and add new ones
            if ($financerData !== []) {
                $this->userService->syncFinancers($existingUser, $financerData);
            }

            // Soft delete the invited user
            // The invited user is no longer needed after successful merge
            $invitedUser->delete();

            // Refresh the existing user to get updated relationships
            return $existingUser->fresh(['financers', 'roles', 'permissions']);
        });
    }
}
