<?php

namespace Tests\Unit\Security;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Division;
use App\Models\Financer;
use App\Security\AuthorizationContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('security')]
#[Group('authorization')]
class AuthorizationContextTest extends TestCase
{
    use DatabaseTransactions;

    private Financer $financer1;

    private Financer $financer2;

    private Financer $financer3;

    private Financer $financer4;

    protected function setUp(): void
    {
        parent::setUp();

        // Create 4 financers to be reused across all tests
        $this->financer1 = ModelFactory::createFinancer();
        $this->financer2 = ModelFactory::createFinancer();
        $this->financer3 = ModelFactory::createFinancer();
        $this->financer4 = ModelFactory::createFinancer();
    }

    #[Test]
    public function it_initializes_with_default_values(): void
    {
        $context = new AuthorizationContext;

        $this->assertTrue($context->isSelfMode());
        $this->assertFalse($context->isGlobalMode());
        $this->assertFalse($context->isTakeControlMode());
        $this->assertEquals([], $context->financerIds());
        $this->assertEquals([], $context->divisionIds());
        $this->assertEquals([], $context->actorRoles());
        $this->assertNull($context->currentFinancerId());
        $this->assertFalse($context->isHydrated());
    }

    #[Test]
    public function it_can_be_hydrated_with_authorization_data(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: new AuthorizationMode('global'),
            financerIds: ['financer-1', 'financer-2'],
            divisionIds: ['division-1'],
            actorRoles: ['god', 'hexeko_admin'],
            currentFinancer: 'financer-1'
        );

        $this->assertTrue($context->isGlobalMode());
        $this->assertFalse($context->isSelfMode());
        $this->assertFalse($context->isTakeControlMode());
        $this->assertEquals(['financer-1', 'financer-2'], $context->financerIds());
        $this->assertEquals(['division-1'], $context->divisionIds());
        $this->assertEquals(['god', 'hexeko_admin'], $context->actorRoles());
        $this->assertEquals('financer-1', $context->currentFinancerId());
        $this->assertTrue($context->isHydrated());
    }

    #[Test]
    public function it_can_accept_string_mode_in_hydrate(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'take_control',
            financerIds: ['financer-1'],
            divisionIds: ['division-1'],
            actorRoles: ['god']
        );

        $this->assertTrue($context->isTakeControlMode());
        $this->assertInstanceOf(AuthorizationMode::class, $context->mode());
    }

    #[Test]
    public function it_normalizes_financer_ids_as_array_values(): void
    {
        $context = new AuthorizationContext;

        // Test with associative array input
        $context->hydrate(
            mode: 'self',
            financerIds: [0 => 'f1', 2 => 'f2', 5 => 'f3'],
            divisionIds: [],
            actorRoles: []
        );

        $result = $context->financerIds();

        // Should return indexed array (list<string>)
        $this->assertEquals(['f1', 'f2', 'f3'], $result);
        // Keys are re-indexed: 0 => 'f1', 1 => 'f2', 2 => 'f3'
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
    }

    #[Test]
    public function it_returns_current_financer_when_explicitly_set(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'take_control',
            financerIds: ['financer-1', 'financer-2'],
            divisionIds: [],
            actorRoles: [],
            currentFinancer: 'financer-2'
        );

        $this->assertEquals('financer-2', $context->currentFinancerId());
    }

    #[Test]
    public function it_returns_null_when_current_financer_not_set(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'global',
            financerIds: ['financer-1', 'financer-2', 'financer-3'],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertNull($context->currentFinancerId());
    }

    #[Test]
    public function it_rejects_financer_filter_outside_user_scope(): void
    {
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer1],
            ],
        ]);
        $user->load('financers', 'roles');

        request()->query->set('financer_id', $this->financer2->id);

        $context = new AuthorizationContext;

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You are not allowed to access the requested financer_id.');

        $context->hydrateFromRequest($user);
    }

    #[Test]
    public function it_allows_non_admin_to_filter_within_own_financers(): void
    {
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer1],
                ['financer' => $this->financer2],
            ],
        ]);
        $user->load('financers', 'roles');

        request()->query->set('financer_id', $this->financer1->id);

        $context = new AuthorizationContext;
        $context->hydrateFromRequest($user);

        $this->assertTrue($context->isSelfMode());
        $this->assertEquals([$this->financer1->id], $context->financerIds());
        $this->assertEquals([$this->financer1->division_id], $context->divisionIds());
    }

    #[Test]
    public function it_allows_admin_to_take_control_scope_with_financer_filter(): void
    {
        $admin = ModelFactory::createUser();
        ModelFactory::createRole(['name' => RoleDefaults::GOD]);
        setPermissionsTeamId($admin->team_id);
        $admin->assignRole(RoleDefaults::GOD);
        $admin->load('financers', 'roles');

        request()->query->set('financer_id', [$this->financer2->id, $this->financer3->id]);

        $context = new AuthorizationContext;
        $context->hydrateFromRequest($admin);

        $this->assertTrue($context->isTakeControlMode());
        $this->assertEqualsCanonicalizing(
            [$this->financer2->id, $this->financer3->id],
            $context->financerIds()
        );
    }

    #[Test]
    public function it_allows_division_admins_to_take_control_within_their_division(): void
    {
        $division = ModelFactory::createDivision();
        $primaryFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $siblingFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $admin = ModelFactory::createUser([
            'financers' => [
                ['financer' => $primaryFinancer],
            ],
        ]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_SUPER_ADMIN]);
        setPermissionsTeamId($admin->team_id);
        $admin->assignRole(RoleDefaults::DIVISION_SUPER_ADMIN);
        $admin->load('financers', 'roles');

        request()->query->set('financer_id', $siblingFinancer->id);

        $context = new AuthorizationContext;
        $context->hydrateFromRequest($admin);

        $this->assertTrue($context->isTakeControlMode());
        $this->assertEquals([$siblingFinancer->id], $context->financerIds());
        $this->assertEquals([$division->id], $context->divisionIds());
    }

    #[Test]
    public function it_rejects_division_admin_take_control_outside_their_division(): void
    {
        $division = ModelFactory::createDivision();
        $primaryFinancer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $foreignFinancer = ModelFactory::createFinancer();

        $admin = ModelFactory::createUser([
            'financers' => [
                ['financer' => $primaryFinancer],
            ],
        ]);
        ModelFactory::createRole(['name' => RoleDefaults::DIVISION_ADMIN]);
        setPermissionsTeamId($admin->team_id);
        $admin->assignRole(RoleDefaults::DIVISION_ADMIN);
        $admin->load('financers', 'roles');

        request()->query->set('financer_id', $foreignFinancer->id);

        $context = new AuthorizationContext;

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('You are not allowed to access the requested financer_id.');

        $context->hydrateFromRequest($admin);
    }

    #[Test]
    public function it_returns_null_for_current_financer_when_no_financers_accessible(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertNull($context->currentFinancerId());
    }

    #[Test]
    public function it_can_check_if_financer_is_accessible(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: ['financer-1', 'financer-2'],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertTrue($context->canAccessFinancer('financer-1'));
        $this->assertTrue($context->canAccessFinancer('financer-2'));
        $this->assertFalse($context->canAccessFinancer('financer-3'));
        $this->assertFalse($context->canAccessFinancer('non-existent'));
    }

    #[Test]
    public function it_can_check_if_division_is_accessible(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'global',
            financerIds: [],
            divisionIds: ['division-1', 'division-2'],
            actorRoles: []
        );

        $this->assertTrue($context->canAccessDivision('division-1'));
        $this->assertTrue($context->canAccessDivision('division-2'));
        $this->assertFalse($context->canAccessDivision('division-3'));
        $this->assertFalse($context->canAccessDivision('non-existent'));
    }

    #[Test]
    public function it_throws_exception_when_asserting_inaccessible_financer(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: ['financer-1'],
            divisionIds: [],
            actorRoles: []
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Financer financer-999 is outside your authorization scope');

        $context->assertFinancer('financer-999');
    }

    #[Test]
    public function it_does_not_throw_when_asserting_accessible_financer(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: ['financer-1'],
            divisionIds: [],
            actorRoles: []
        );

        $context->assertFinancer('financer-1');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_throws_exception_when_asserting_inaccessible_division(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: ['division-1'],
            actorRoles: []
        );

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Division division-999 is outside your authorization scope');

        $context->assertDivision('division-999');
    }

    #[Test]
    public function it_does_not_throw_when_asserting_accessible_division(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: ['division-1'],
            actorRoles: []
        );

        $context->assertDivision('division-1');

        $this->assertTrue(true);
    }

    #[Test]
    public function it_correctly_identifies_self_mode(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertTrue($context->isSelfMode());
        $this->assertFalse($context->isGlobalMode());
        $this->assertFalse($context->isTakeControlMode());
        $this->assertEquals(AuthorizationMode::SELF, $context->mode()->value);
    }

    #[Test]
    public function it_correctly_identifies_global_mode(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'global',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertTrue($context->isGlobalMode());
        $this->assertFalse($context->isSelfMode());
        $this->assertFalse($context->isTakeControlMode());
        $this->assertEquals(AuthorizationMode::GLOBAL, $context->mode()->value);
    }

    #[Test]
    public function it_correctly_identifies_take_control_mode(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'take_control',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertTrue($context->isTakeControlMode());
        $this->assertFalse($context->isSelfMode());
        $this->assertFalse($context->isGlobalMode());
        $this->assertEquals(AuthorizationMode::TAKE_CONTROL, $context->mode()->value);
    }

    #[Test]
    public function it_reports_as_not_hydrated_when_empty(): void
    {
        $context = new AuthorizationContext;

        $this->assertFalse($context->isHydrated());

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertFalse($context->isHydrated());
    }

    #[Test]
    public function it_reports_as_hydrated_when_has_financer_data(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: ['financer-1'],
            divisionIds: [],
            actorRoles: []
        );

        $this->assertTrue($context->isHydrated());
    }

    #[Test]
    public function it_reports_as_hydrated_when_has_division_data(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: ['division-1'],
            actorRoles: []
        );

        $this->assertTrue($context->isHydrated());
    }

    #[Test]
    public function it_stores_actor_roles(): void
    {
        $context = new AuthorizationContext;

        $context->hydrate(
            mode: 'global',
            financerIds: [],
            divisionIds: [],
            actorRoles: ['god', 'hexeko_admin', 'beneficiary']
        );

        $this->assertEquals(['god', 'hexeko_admin', 'beneficiary'], $context->actorRoles());
    }

    #[Test]
    public function it_returns_target_user_active_financers_when_viewing_own_profile(): void
    {
        $context = new AuthorizationContext;
        $context->hydrate(
            mode: 'self',
            financerIds: [$this->financer1->id, $this->financer2->id, $this->financer3->id],
            divisionIds: [],
            actorRoles: []
        );

        $user = ModelFactory::createUser();
        $user->financers()->attach($this->financer1->id, ['active' => true]);
        $user->financers()->attach($this->financer2->id, ['active' => false]);
        $user->financers()->attach($this->financer3->id, ['active' => true]);
        $user->load('financers');

        $result = $context->getAccessibleFinancersFor($user, $user);

        $this->assertCount(2, $result);
        $this->assertEquals($this->financer1->id, $result[0]->id);
        $this->assertEquals($this->financer3->id, $result[1]->id);
    }

    #[Test]
    public function it_returns_only_shared_active_financers_when_viewing_other_user(): void
    {
        $context = new AuthorizationContext;
        $context->hydrate(
            mode: AuthorizationMode::SELF,
            financerIds: [$this->financer1->id, $this->financer2->id, $this->financer3->id, $this->financer4->id],
            divisionIds: [],
            actorRoles: []
        );

        $viewer = ModelFactory::createUser();
        $viewer->financers()->attach($this->financer1->id, ['active' => true]);
        $viewer->financers()->attach($this->financer2->id, ['active' => false]);
        $viewer->load('financers');

        $target = ModelFactory::createUser();
        $target->financers()->attach($this->financer1->id, ['active' => true]);
        $target->financers()->attach($this->financer2->id, ['active' => true]);
        $target->financers()->attach($this->financer3->id, ['active' => true]);
        $target->load('financers');

        $result = $context->getAccessibleFinancersFor($target, $viewer);

        $this->assertCount(1, $result);
        $this->assertEquals($this->financer1->id, $result[0]->id);
    }

    #[Test]
    public function it_allows_admin_viewers_to_see_all_scoped_financers(): void
    {
        $context = new AuthorizationContext;
        $context->hydrate(
            mode: AuthorizationMode::GLOBAL,
            financerIds: [$this->financer1->id, $this->financer2->id],
            divisionIds: [],
            actorRoles: [RoleDefaults::GOD]
        );

        $viewer = ModelFactory::createUser();
        ModelFactory::createRole(['name' => RoleDefaults::GOD]);
        setPermissionsTeamId($viewer->team_id);
        $viewer->assignRole(RoleDefaults::GOD);
        $viewer->load('financers');

        $target = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer1],
                ['financer' => $this->financer2],
            ],
        ]);
        $target->load('financers');

        $result = $context->getAccessibleFinancersFor($target, $viewer);

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->financer1->id, $this->financer2->id],
            array_map(static fn (Financer $financer) => $financer->id, $result)
        );
    }

    #[Test]
    public function it_respects_authorization_scope_when_filtering_financers(): void
    {
        $context = new AuthorizationContext;
        // User only has access to financer1
        $context->hydrate(
            mode: 'take_control',
            financerIds: [$this->financer1->id],
            divisionIds: [],
            actorRoles: []
        );

        $user = ModelFactory::createUser();
        $user->financers()->attach($this->financer1->id, ['active' => true]);
        $user->financers()->attach($this->financer2->id, ['active' => true]);
        $user->financers()->attach($this->financer3->id, ['active' => true]);
        $user->load('financers');

        $result = $context->getAccessibleFinancersFor($user, $user);

        $this->assertCount(1, $result);
        $this->assertEquals($this->financer1->id, $result[0]->id);
    }

    #[Test]
    public function it_returns_empty_array_when_no_financers_in_authorization_scope(): void
    {
        $context = new AuthorizationContext;
        $context->hydrate(
            mode: 'self',
            financerIds: [],
            divisionIds: [],
            actorRoles: []
        );

        $user = ModelFactory::createUser();
        $user->financers()->attach($this->financer1->id, ['active' => true]);
        $user->load('financers');

        $result = $context->getAccessibleFinancersFor($user, $user);

        $this->assertEmpty($result);
    }

    #[Test]
    public function it_applies_scope_for_division_on_models_with_trait(): void
    {
        $divisionA = ModelFactory::createDivision();
        $divisionB = ModelFactory::createDivision();
        $financerA = ModelFactory::createFinancer(['division_id' => $divisionA->id]);
        $financerB = ModelFactory::createFinancer(['division_id' => $divisionB->id]);

        $context = new AuthorizationContext;
        $context->hydrate(
            mode: AuthorizationMode::SELF,
            financerIds: [$financerA->id],
            divisionIds: [$divisionA->id],
            actorRoles: [],
            currentFinancer: $financerA->id
        );

        $ids = $context->scopeForDivision(Financer::query())
            ->pluck('financers.id')
            ->all();

        $this->assertContains($financerA->id, $ids);
        $this->assertNotContains($financerB->id, $ids);
    }

    #[Test]
    public function it_logs_warning_when_scope_for_division_missing_trait(): void
    {
        $division = ModelFactory::createDivision();
        $context = new AuthorizationContext;
        $context->hydrate(
            mode: AuthorizationMode::SELF,
            financerIds: [],
            divisionIds: [$division->id],
            actorRoles: []
        );

        Log::spy();

        $context->scopeForDivision(Division::query());

        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'scopeForDivision') && array_key_exists('model', $context);
            });
    }

    protected function tearDown(): void
    {
        request()->query->remove('financer_id');
        parent::tearDown();
    }
}
