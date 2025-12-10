<?php

declare(strict_types=1);

namespace App\Services\Models;

use App\Actions\User\UpdateUserLanguageAction;
use App\Models\Role;
use App\Models\User;
use App\Traits\ServiceTraits\User\SyncFinancersTrait;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class UserService
{
    use SyncFinancersTrait;

    /**
     * @param  array<string>  $relations
     * @return Collection<string, array{total_items: int<0, max>}|Collection<int, User>>
     */
    public function all(
        int $perPage = 20,
        int $page = 1,
        array $relations = [],
        bool $paginationRequired = true
    ): Collection {
        /** @var Collection<int, User> */
        $items = User::query()
            ->with($relations)
            ->pipeFiltered()
            ->get();

        $total = $items->count();

        if ($paginationRequired) {
            $items = $items->forPage($page, $perPage);
        }

        return collect([
            'items' => $items,
            'meta' => [
                'total_items' => $total,
            ],
        ]);
    }

    /**
     * @param  array<string>  $relations
     */
    public function find(string $id, array $relations = []): User
    {
        // Direct database access (repository pattern removed)
        $user = User::with($relations)
            ->where('id', $id)
            ->userRelated()
            ->first();

        if (! $user instanceof User) {
            throw new ModelNotFoundException('User not found');
        }

        return $user;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return User
     */
    public function create(array $data)
    {
        $profileImage = $data['profile_image'] ?? null;
        unset($data['profile_image']);

        // Extract locale but keep it for User::create() to avoid NULL → value update
        $locale = $data['locale'] ?? null;

        $user = User::create($data);

        if (is_string($profileImage) && $profileImage !== '') {
            $user->addMediaFromBase64($profileImage)->toMediaCollection('profile_image');
        }

        // Handle language update separately only if locale was provided
        // updateUserLanguage() will skip save if locale hasn't changed
        if ($locale !== null && is_string($locale)) {
            $this->updateUserLanguage($user, $locale);
        }

        return $user;
    }

    /**
     * Update a user
     *
     * @param  array<string,mixed>  $data
     */
    public function update(User $user, array $data): User
    {
        $profileImage = $data['profile_image'] ?? null;
        unset($data['profile_image']);

        $user->update($data);

        if (is_string($profileImage) && $profileImage !== '') {
            $user->addMediaFromBase64($profileImage)->toMediaCollection('profile_image');
        }

        return $user;
    }

    /**
     * Delete a user
     */
    public function delete(User $user): bool
    {
        return (bool) $user->delete();
    }

    /*
     * Assign a role to a user
     * @param  User  $user
     * @param  Role  $role
     * @return User
     *
     * */

    /**
     * Deactivate a user for a specific financer
     */
    public function deactivateForFinancer(User $user, string $financerId): bool
    {
        // Check if the user is attached to this financer
        $financer = $user->financers()->where('financer_id', $financerId)->first();

        if (! $financer) {
            return false;
        }

        // Update the pivot to set active to false
        $user->financers()->updateExistingPivot($financerId, [
            'active' => false,
        ]);

        return true;
    }

    /**
     * Activate a user for a specific financer
     */
    public function activateForFinancer(User $user, string $financerId): bool
    {
        // Check if the user is attached to this financer
        $financer = $user->financers()->where('financer_id', $financerId)->first();

        if (! $financer) {
            return false;
        }

        // Update the pivot to set active to true
        $user->financers()->updateExistingPivot($financerId, [
            'active' => true,
        ]);

        return true;
    }

    /**
     * Toggle user activation status for a specific financer
     */
    public function toggleActivationForFinancer(User $user, string $financerId): bool
    {
        // Check if the user is attached to this financer
        $financer = $user->financers()->where('financer_id', $financerId)->first();

        if (! $financer) {
            return false;
        }

        // Get current status and toggle it
        $pivot = $financer->pivot;
        $newStatus = $pivot !== null ? ! $pivot->active : true;

        // Update the pivot with the new status
        $user->financers()->updateExistingPivot($financerId, [
            'active' => $newStatus,
        ]);

        return $newStatus;
    }

    /*
     * Remove a role from a user
     * @param  User  $user
     * @param  Role  $role
     * @return User
     * */

    public function assignRole(User $user, Role $role): User
    {
        // Check if the authenticated user may assign the role to this user else throw an exception
        $user->checkUserBelongsToAuthOrganisation();

        // Check if the authenticated user may edit the role else throw an exception
        $role->canBeModifiedByAuth();

        setPermissionsTeamId($user->team_id);
        $user->assignRole($role->name);

        return $user;
    }

    public function removeRole(User $user, Role $role): User
    {
        // Check if the authenticated user can assign the role else throw an exception
        $user->checkUserBelongsToAuthOrganisation();

        $role->canBeModifiedByAuth();

        setPermissionsTeamId($user->team_id);
        $user->removeRole($role->name);

        return $user;
    }

    /**
     * Update user language for specific financer
     * This updates financer_user.language and syncs user.locale
     *
     * @deprecated Use financers.*.pivot.language in PUT /api/v1/users/{id} instead
     */
    public function updateUserLanguage(User $user, string $language): void
    {
        // Get current financer ID
        $financerId = activeFinancerID();

        if (! in_array($financerId, ['', '0', []], true)) {
            // Update language via action
            app(UpdateUserLanguageAction::class)->execute($user, $language);
        } elseif ($user->locale !== $language) {
            // No context, only update user.locale if it has changed
            $user->locale = $language;
            $user->save();
            Log::info('Updated user locale without financer context', [
                'user_id' => $user->id,
                'new_locale' => $language,
            ]);
        }
    }

    /**
     * Update user settings including language
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateSettings(User $user, array $payload): User
    {
        // Handle language update via new system
        if (array_key_exists('locale', $payload)) {
            $locale = $payload['locale'];
            if (is_string($locale)) {
                $this->updateUserLanguage($user, $locale);
            }
            unset($payload['locale']); // Remove from payload to avoid double update
        }

        // Handle other settings updates
        if ($payload !== []) {
            $user = $this->update($user, $payload);
        }

        $freshUser = $user->fresh();

        return $freshUser !== null ? $freshUser : $user;
    }
}
