<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Integrations\HRTools\Models\Link;
use App\Integrations\HRTools\Policies\LinkPolicy;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('link')]
#[Group('policy')]
class LinkPolicyTest extends TestCase
{
    use DatabaseTransactions;

    private LinkPolicy $policy;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new LinkPolicy;
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        foreach ([PermissionDefaults::READ_HRTOOLS, PermissionDefaults::CREATE_HRTOOLS, PermissionDefaults::UPDATE_HRTOOLS, PermissionDefaults::DELETE_HRTOOLS] as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'api',
            ]);
        }

        $this->financer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);
        $this->otherFinancer = Financer::factory()->create([
            'division_id' => ModelFactory::createDivision()->id,
        ]);
        $this->financer->load('division');
        $this->otherFinancer->load('division');

        $this->resetAuthorizationContext();
    }

    protected function tearDown(): void
    {
        $this->resetAuthorizationContext();
        parent::tearDown();
    }

    #[Test]
    public function user_without_permission_cannot_view_links(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);

        $this->assertFalse($this->policy->viewAny($user));
    }

    #[Test]
    public function user_with_permission_can_view_links_in_scope(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->givePermissionTo(PermissionDefaults::READ_HRTOOLS);
        $link = Link::factory()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertTrue($this->policy->viewAny($user));
        $this->assertTrue($this->policy->view($user, $link));
    }

    #[Test]
    public function user_cannot_view_links_outside_scope_even_with_permission(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->givePermissionTo(PermissionDefaults::READ_HRTOOLS);
        $link = Link::factory()->create([
            'financer_id' => $this->otherFinancer->id,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertFalse($this->policy->view($user, $link));
    }

    #[Test]
    public function user_can_create_links_for_authorized_financer(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->givePermissionTo(PermissionDefaults::CREATE_HRTOOLS);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertTrue($this->policy->create($user, $this->financer->id));
        $this->assertFalse($this->policy->create($user, $this->otherFinancer->id));
    }

    #[Test]
    public function user_can_update_or_delete_links_within_scope(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->givePermissionTo(PermissionDefaults::UPDATE_HRTOOLS);
        $user->givePermissionTo(PermissionDefaults::DELETE_HRTOOLS);
        $link = Link::factory()->create([
            'financer_id' => $this->financer->id,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertTrue($this->policy->update($user, $link));
        $this->assertTrue($this->policy->delete($user, $link));
    }

    #[Test]
    public function user_cannot_update_or_delete_links_outside_scope(): void
    {
        $user = User::factory()->create(['team_id' => $this->team->id]);
        $user->givePermissionTo(PermissionDefaults::UPDATE_HRTOOLS);
        $user->givePermissionTo(PermissionDefaults::DELETE_HRTOOLS);
        $link = Link::factory()->create([
            'financer_id' => $this->otherFinancer->id,
        ]);

        $this->hydrateAuthorizationContext([$this->financer]);

        $this->assertFalse($this->policy->update($user, $link));
        $this->assertFalse($this->policy->delete($user, $link));
    }

    /**
     * @param  array<int, Financer>  $financers
     */
    private function hydrateAuthorizationContext(array $financers): void
    {
        $financerIds = collect($financers)->pluck('id')->filter()->values()->all();
        $divisionIds = collect($financers)->pluck('division_id')->filter()->values()->all();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            $financerIds,
            $divisionIds,
            [],
            $financerIds[0] ?? null
        );
    }

    private function resetAuthorizationContext(): void
    {
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [],
            [],
            [],
            null
        );
    }
}
