<?php

namespace App\Actions\User\CRUD;

use App\Models\User;
use App\Services\Models\UserProfileImageService;
use App\Services\Models\UserService;
use Arr;
use DB;
use Log;
use Throwable;

class UpdateUserAction
{
    public function __construct(protected UserService $userService, protected UserProfileImageService $profileImageService) {}

    /**
     * Run action to update a user.
     *
     * @param  array<string,mixed>  $validatedData
     */
    public function handle(User $user, array $validatedData): User
    {
        try {
            return DB::transaction(function () use ($user, $validatedData) {
                $financers = Arr::pull($validatedData, 'financers');
                $profileImage = Arr::pull($validatedData, 'profile_image');
                $locale = Arr::pull($validatedData, 'locale');
                $departments = Arr::pull($validatedData, 'departments');
                $sites = Arr::pull($validatedData, 'sites');
                $managers = Arr::pull($validatedData, 'managers');
                $contractTypes = Arr::pull($validatedData, 'contract_types');
                $tags = Arr::pull($validatedData, 'tags');

                // Handle language update separately via UserService
                // @deprecated This approach is deprecated. Use financers.*.pivot.language instead
                if ($locale !== null && is_string($locale)) {
                    $this->userService->updateUserLanguage($user, $locale);
                }

                // Update other user fields
                if ($validatedData !== []) {
                    $this->userService->update($user, $validatedData);
                }

                if (is_string($profileImage)) {
                    $this->profileImageService->updateProfileImage($user, $profileImage);
                }

                if (is_array($financers)) {
                    // @phpstan-ignore-next-line
                    $this->userService->syncFinancers($user, $financers);
                }

                if (is_array($departments)) {
                    $user->departments()->sync($departments);
                }

                if (is_array($sites)) {
                    $user->sites()->sync($sites);
                }

                if (is_array($managers)) {
                    $user->managers()->sync($managers);
                }

                if (is_array($contractTypes)) {
                    $user->contractTypes()->sync($contractTypes);
                }

                if (is_array($tags)) {
                    $user->tags()->sync($tags);
                }

                return $user->refresh();
            });
        } catch (Throwable $e) {
            Log::error($e->getMessage(), ['trace' => $e->getTrace()]);
            throw $e;
        }
    }
}
