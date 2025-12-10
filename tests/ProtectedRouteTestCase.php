<?php

namespace Tests;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Http\Middleware\AdminCognitoMiddleware;
use App\Http\Middleware\CheckActiveFinancerMiddleware;
use App\Http\Middleware\CheckPermissionAttribute;
use App\Http\Middleware\CognitoAuthMiddleware;
use App\Http\Middleware\TenantGuard;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Artisan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use Schema;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\Facades\ModelFactory;

#[Group('auth')]
abstract class ProtectedRouteTestCase extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $auth;

    protected ?Division $currentDivision = null;

    protected ?Financer $currentFinancer = null;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected bool $checkActiveFinancer = false;

    protected bool $checkTenantGuard = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Flushing is now handled centrally in Tests\TestCase via attribute

        if (! $this->checkPermissions) {
            $this->withoutMiddleware(CheckPermissionAttribute::class);
        }
        if (! $this->checkAuth) {
            $this->withoutMiddleware(CognitoAuthMiddleware::class);
            $this->withoutMiddleware(AdminCognitoMiddleware::class);
        }
        if (! $this->checkActiveFinancer) {
            $this->withoutMiddleware(CheckActiveFinancerMiddleware::class);
        }
        if (! $this->checkTenantGuard) {
            $this->withoutMiddleware(TenantGuard::class);
        }

        //        Artisan::call('app:reset-role-permissions');
    }

    /**
     * Override to enable table flushing for specific test classes
     */
    // Removed shouldFlushTables and attribute resolution; central in base TestCase

    /**
     * Create an authenticated user with optional division and financer
     *
     * @param  string  $role  User role
     * @param  Team|null  $team  Optional team
     * @param  array  $financerData  Additional financer data
     * @param  bool  $withContext  Auto-hydrate authorization context
     * @param  bool  $returnDetails  Populate $this->currentDivision and $this->currentFinancer
     */
    protected function createAuthUser(
        string $role = RoleDefaults::BENEFICIARY,
        ?Team $team = null,
        array $financerData = [],
        bool $withContext = false,
        bool $returnDetails = false,
        array $userAttributes = []
    ): User {
        if (is_null($team)) {
            if (! Schema::hasTable('teams')) {
                Artisan::call('migrate');
            }
            $team = ModelFactory::createTeam();
        }

        setPermissionsTeamId($team->id);

        $roleExists = Role::where('team_id', $team->id)
            ->where('name', $role)
            ->where('guard_name', 'api')
            ->first();

        if (! $roleExists || Permission::count() == 0) {
            $this->createRoleAndPermissions($role, $team);
        }

        $division = ModelFactory::createDivision();

        $financer = ModelFactory::createFinancer(['division_id' => $division->id, ...$financerData]);
        $user = ModelFactory::createUser(
            [
                'financers' => [
                    ['financer' => $financer], // active by default
                ],
                ...$userAttributes,
            ]
        );
        $user->load('financers');

        $user->assignRole($role);
        $user->load('roles');

        if ($withContext) {
            $this->hydrateAuthorizationContext($user);
        }

        if ($returnDetails) {
            $this->currentDivision = $division;
            $this->currentFinancer = $financer;
        }

        return $user;
    }

    protected function createRoleAndPermissions($role, $team = null)
    {
        config(['auth.defaults.guard' => 'api']);

        if (is_null($team)) {
            $team = ModelFactory::createTeam();
        }

        // Ensure permissions exist (seeded once per schema in TestCase); if not, create idempotently
        $this->createPermissions($role);

        $role = Role::firstOrCreate(['name' => $role, 'team_id' => $team->id, 'guard_name' => 'api']);

        $rolePermissions = RoleDefaults::getPermissionsByRole($role->name);
        $role->givePermissionTo($rolePermissions);

        Artisan::call('cache:clear');
        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createPermissions($role = null): array
    {
        // Idempotent ensure of permissions; no destructive deletes in tests
        $permissionNames = is_null($role)
            ? PermissionDefaults::asArray()
            : RoleDefaults::getPermissionsByRole($role);

        $ensured = [];
        foreach ($permissionNames as $name) {
            Permission::firstOrCreate(
                ['name' => $name, 'guard_name' => 'api'],
                ['is_protected' => true]
            );
            $ensured[] = $name;
        }

        return $ensured;
    }

    /**
     * Hydrate authorization context with user data
     * Simplifies the setup of authorization context in tests
     *
     * @param  User|null  $user  The user to hydrate the context for (defaults to $this->auth)
     * @param  AuthorizationMode|string  $mode  Authorization mode (SELF, GLOBAL, TAKE_CONTROL)
     */
    protected function hydrateAuthorizationContext(
        ?User $user = null,
        AuthorizationMode|string $mode = AuthorizationMode::SELF,
        ?Financer $currentFinancer = null
    ): void {
        $user = $user ?? $this->auth;

        if (! $user instanceof User) {
            throw new InvalidArgumentException('User must be provided either as parameter or via $this->auth property');
        }

        $user->loadMissing(['financers', 'roles']);

        // Get the first active financer or just the first financer
        $activeFinancer = $user->financers->first(fn ($f) => $f->pivot->active ?? false);

        $currentFinancerId = $currentFinancer?->id ?? $activeFinancer?->id ?? $user->financers->first()?->id;

        authorizationContext()->hydrate(
            $mode,
            $user->financers->pluck('id')->toArray(),
            $user->financers->pluck('division_id')->toArray(),
            $user->roles->pluck('name')->toArray(),
            $currentFinancerId
        );
    }
}
