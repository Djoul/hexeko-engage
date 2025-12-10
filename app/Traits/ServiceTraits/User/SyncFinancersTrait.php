<?php

namespace App\Traits\ServiceTraits\User;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use InvalidArgumentException;

trait SyncFinancersTrait
{
    private const DEFAULT_ACTIVE_STATUS = false;

    /**
     * Sync the financers for the user
     *
     * @param  array<int, array{id: string, pivot?: array{active?: bool, from?: string, to?: string, sirh_id?: string, role?: string}}>  $financerData
     */
    public function syncFinancers(User $user, array $financerData): void
    {
        $financerIds = $this->extractFinancerIds($financerData);

        // Validate division constraints
        $this->validateFinancerDivisions($user, $financerIds);

        $financersWithAttributes = $this->transformFinancerData($financerData);

        // Build sync array with full pivot data including required 'role' field
        $syncData = [];
        foreach ($financersWithAttributes as $financer) {
            if (is_array($financer) && array_key_exists('id', $financer) && is_string($financer['id'])) {
                $pivotData = [
                    'active' => $financer['active'] ?? true,
                ];

                // Add optional attributes if they exist
                $pivotData['from'] = array_key_exists('from', $financer) ? $financer['from'] : now();

                if (array_key_exists('to', $financer)) {
                    $pivotData['to'] = $financer['to'];
                }

                if (array_key_exists('sirh_id', $financer)) {
                    $pivotData['sirh_id'] = $financer['sirh_id'];
                }

                if (array_key_exists('started_at', $financer)) {
                    $pivotData['started_at'] = $financer['started_at'];
                }

                if (array_key_exists('work_mode_id', $financer)) {
                    $pivotData['work_mode_id'] = $financer['work_mode_id'];
                }

                if (array_key_exists('job_title_id', $financer)) {
                    $pivotData['job_title_id'] = $financer['job_title_id'];
                }

                if (array_key_exists('job_level_id', $financer)) {
                    $pivotData['job_level_id'] = $financer['job_level_id'];
                }

                // Handle language if provided
                if (array_key_exists('language', $financer)) {
                    $pivotData['language'] = $financer['language'];
                }

                // Handle role: use provided role or sync from user's current role (REQUIRED FIELD)
                if (array_key_exists('role', $financer)) {
                    // Use the role provided in the pivot data
                    $pivotData['role'] = $financer['role'];
                } else {
                    // Sync user's single role to the pivot table
                    $role = $user->roles()->pluck('name')->first() ?? RoleDefaults::BENEFICIARY;
                    $pivotData['role'] = $role;
                }

                $syncData[$financer['id']] = $pivotData;
            }
        }

        // Sync without detaching, with full pivot data including role
        $user->financers()->syncWithoutDetaching($syncData);
    }

    /**
     * @param  array<int, array{id: string, pivot?: array{active?: bool, from?: string, to?: string, sirh_id?: string, role?: string}}>  $financerData
     * @return array<string>
     */
    private function extractFinancerIds(array $financerData): array
    {
        /** @var array<string> */
        $result = collect($financerData)->pluck('id')->map(fn ($id): string => (string) $id)->toArray();

        return $result;
    }

    /**
     * Transform financer data to include all attributes.
     *
     * @param  array<int, array{id: string, pivot?: array{active?: bool, from?: string, to?: string, sirh_id?: string, role?: string, language?: string}}>  $financerData
     * @return array<int, array{id: string, active: bool, from?: string, to?: string, sirh_id?: string, role?: string, language?: string}>
     */
    private function transformFinancerData(array $financerData): array
    {
        /** @var array<int, array{id: string, active: bool, from?: string, to?: string, sirh_id?: string, role?: string, language?: string}> */
        $result = collect($financerData)->map(function (array $financer): array {
            $active = is_bool($financer['pivot']['active'] ?? null)
                ? $financer['pivot']['active']
                : self::DEFAULT_ACTIVE_STATUS;

            $result = [
                'id' => $financer['id'],
                'active' => $active,
            ];

            // Add optional attributes if they exist

            if (array_key_exists('pivot', $financer) && array_key_exists('from', $financer['pivot'])) {
                $result['from'] = $financer['pivot']['from'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('to', $financer['pivot'])) {
                $result['to'] = $financer['pivot']['to'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('sirh_id', $financer['pivot'])) {
                $result['sirh_id'] = $financer['pivot']['sirh_id'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('started_at', $financer['pivot'])) {
                $result['started_at'] = $financer['pivot']['started_at'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('work_mode_id', $financer['pivot'])) {
                $result['work_mode_id'] = $financer['pivot']['work_mode_id'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('job_title_id', $financer['pivot'])) {
                $result['job_title_id'] = $financer['pivot']['job_title_id'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('job_level_id', $financer['pivot'])) {
                $result['job_level_id'] = $financer['pivot']['job_level_id'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('role', $financer['pivot'])) {
                $result['role'] = $financer['pivot']['role'];
            }

            if (array_key_exists('pivot', $financer) && array_key_exists('language', $financer['pivot'])) {
                $result['language'] = $financer['pivot']['language'];
            }

            return $result;
        })->toArray();

        return $result;
    }

    /**
     * Validate that new financers belong to the same division as existing ones
     *
     * @param  array<string>  $newFinancerIds
     *
     * @throws InvalidArgumentException
     */
    private function validateFinancerDivisions(User $user, array $newFinancerIds): void
    {
        // Check if the current authenticated user has GOD or HEXEKO_ADMIN role - they can bypass division restrictions
        $authUser = auth()->user();
        if ($authUser && $authUser->hasAnyRole([RoleDefaults::GOD, RoleDefaults::HEXEKO_ADMIN, RoleDefaults::HEXEKO_SUPER_ADMIN])) {
            return;
        }

        // Get user's existing financers
        $existingFinancers = $user->financers;

        // If user has no existing financers, allow initial assignment (e.g., during user creation from webhook)
        // This is needed for new user creation workflows
        if ($existingFinancers->isEmpty() && $newFinancerIds !== []) {
            // Allow initial financer assignment for new users
            return;
        }

        // If trying to remove all financers from an existing user
        if ($newFinancerIds === [] && ! $existingFinancers->isEmpty()) {
            throw new InvalidArgumentException('User must have at least one financer');
        }

        // Get the divisions of existing financers
        $existingDivisionIds = $existingFinancers->pluck('division_id')->unique()->toArray();

        // Get the new financers
        $newFinancers = Financer::whereIn('id', $newFinancerIds)->get();

        // Check if all new financers belong to the same divisions as existing ones
        foreach ($newFinancers as $newFinancer) {
            if (! in_array($newFinancer->division_id, $existingDivisionIds)) {
                throw new InvalidArgumentException('Cannot add financer from different division');
            }
        }
    }
}
