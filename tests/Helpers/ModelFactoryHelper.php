<?php

namespace Tests\Helpers;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Arr;
use Illuminate\Support\Str;

class ModelFactoryHelper
{
    // region Role -->
    public static function createRole(array $data = [], $count = null)
    {
        $roleFactory = Role::factory();

        if ($count) {
            $roleFactory = $roleFactory->count($count);
        }
        $data['team_id'] = $data['team_id'] ?? Team::firstOr(fn () => self::createTeam())->id;

        setPermissionsTeamId($data['team_id']);

        return $roleFactory->create($data);
    }

    public static function makeRole(array $data = [], $count = null)
    {
        $roleFactory = Role::factory();

        if ($count) {
            $roleFactory = $roleFactory->count($count);
        }
        $data['team_id'] = $data['team_id'] ?? self::createTeam()->id;

        return $roleFactory->make($data);
    }
    // endregion

    // region Role -->
    public static function createPermission(array $data = [], $count = null)
    {
        // If a name is provided, avoid duplicates by firstOrCreate on (name, guard_name)
        if (isset($data['name'])) {
            $attributes = [
                'name' => $data['name'],
                'guard_name' => $data['guard_name'] ?? 'api',
            ];

            $values = [
                'id' => $data['id'] ?? Str::uuid()->toString(),
            ];

            return Permission::firstOrCreate($attributes, $values);
        }

        $permissionFactory = Permission::factory();
        if ($count) {
            $permissionFactory = $permissionFactory->count($count);
        }

        return $permissionFactory->create($data);
    }

    public static function makePermission(array $data = [], $count = null)
    {
        $permissionFactory = Permission::factory();

        if ($count) {
            $permissionFactory = $permissionFactory->count($count);
        }

        return $permissionFactory->make($data);
    }
    // endregion

    // region Team -->
    public static function createTeam(array $data = [], $count = null)
    {
        $teamFactory = Team::factory();

        if ($count) {
            $teamFactory = $teamFactory->count($count);
        }

        return $teamFactory->create($data);
    }

    public static function makeTeam(array $data = [], $count = null)
    {
        $teamFactory = Team::factory();

        if ($count) {
            $teamFactory = $teamFactory->count($count);
        }

        return $teamFactory->make($data);
    }
    // endregion

    // region Division -->
    const USER_EXCEPTED_KEYS = ['financers', 'financer', 'password_confirmation'];

    public static function createDivision(array $data = [], $count = null)
    {
        $divisionFactory = Division::factory();

        if ($count) {
            $divisionFactory = $divisionFactory->count($count);
        }

        return $divisionFactory->create($data);
    }

    public static function makeDivision(array $data = [], $count = null)
    {
        $divisionFactory = Division::factory();

        if ($count) {
            $divisionFactory = $divisionFactory->count($count);
        }

        return $divisionFactory->make($data);
    }
    // endregion --

    // region Financer -->

    public static function createFinancer(array $data = [], $count = null)
    {
        //        // Save context only in tests to avoid side effects during test execution
        //        $previousContext = app()->environment('testing')
        //            ? authorizationContext()->toArray()
        //            : null;

        $financerFactory = Financer::factory();

        if ($count) {
            $financerFactory = $financerFactory->count($count);
        }

        $data['division_id'] = $data['division_id'] ?? self::createDivision()->id;

        //        // Restore context only in tests
        //        if ($previousContext !== null) {
        //            authorizationContext()->restore($previousContext);
        //        }

        return $financerFactory->create($data);
    }

    public static function makeFinancer(array $data = [], $count = null)
    {
        $financerFactory = Financer::factory();

        if ($count) {
            $financerFactory = $financerFactory->count($count);
        }

        $data['division_id'] = $data['division_id'] ?? self::createDivision()->id;

        return $financerFactory->make($data);
    }
    // endregion

    // region User -->
    public static function createUser(array $data = [], $attachFinancer = true, $verified = true, $count = null)
    {
        if (! $attachFinancer) {
            abort(400, 'Cannot create user without financer');
        }

        $userFactory = User::factory();

        if ($count) {
            $userFactory = $userFactory->count($count);
        }

        if ($attachFinancer) {

            $financers = $data['financers'] ?? ['financer' => self::createFinancer()];
            $financers = is_array($financers) ? $financers : [$financers];
            foreach ($financers as $financer) {
                $userFactory = $userFactory->hasAttached(
                    $financer['financer'],
                    [
                        'active' => $financer['active'] ?? true,
                        'sirh_id' => $financer['sirh_id'] ?? null,
                        'from' => $financer['from'] ?? now(),
                        'role' => $financer['role'] ?? RoleDefaults::BENEFICIARY,
                    ]);
            }

            return $userFactory->create(
                Arr::except(
                    $data,
                    self::USER_EXCEPTED_KEYS
                )
            );
        }

        $user = $userFactory->create(
            Arr::except(
                $data,
                self::USER_EXCEPTED_KEYS
            )
        );

        // Restore context only in tests
        if ($previousContext !== null) {
            authorizationContext()->restore($previousContext);
        }

        return $user;
    }

    public static function makeUser(array $data = [], $attachFinancer = true, $verified = true, $count = null)
    {
        $userFactory = User::factory();

        if ($count) {
            $userFactory = $userFactory->count($count);
        }

        if ($attachFinancer) {
            $financers = $data['financers'] ?? ['financer' => self::createFinancer()];
            $financers = is_array($financers) ? $financers : [$financers];

            foreach ($financers as $financer) {
                $userFactory = $userFactory->hasAttached($financer['financer'], [
                    'active' => $financer['active'] ?? true,
                    'role' => $financer['role'] ?? RoleDefaults::BENEFICIARY,
                ]);
            }

            return $userFactory->make(
                Arr::except(
                    $data,
                    self::USER_EXCEPTED_KEYS
                )
            );
        }

        return $userFactory->make(Arr::except(
            $data,
            self::USER_EXCEPTED_KEYS
        ));
    }
    // endregion
}
