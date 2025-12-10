<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
class FinancerDivisionAccessTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/financers';

    protected function setUp(): void
    {
        parent::setUp();

        // Set the default guard
        config(['auth.defaults.guard' => 'api']);

        // Create roles with the model factory
    }

    #[Test]
    public function it_shows_all_financers_for_god_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with god role
        $user = $this->createAuthUser(RoleDefaults::GOD, returnDetails: true);

        $response = $this->actingAs($user)->getJson(self::URI);

        $response->assertStatus(200);

        // God role should see all financers in the system
        $responseData = $response->json('data');
        $this->assertIsArray($responseData);

        // Verify our created financers are included
        $ids = collect($responseData)->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertContains($financer3->id, $ids);
        $this->assertContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_all_financers_for_hexeko_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with hexeko_admin role
        $user = $this->createAuthUser(RoleDefaults::HEXEKO_ADMIN, returnDetails: true);
        $response = $this->actingAs($user)->getJson(self::URI);

        $response->assertStatus(200);

        // Hexeko admin role should see all financers in the system
        $responseData = $response->json('data');
        $this->assertIsArray($responseData);

        // Verify our created financers are included
        $ids = collect($responseData)->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertContains($financer3->id, $ids);
        $this->assertContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_all_financers_for_hexeko_super_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with hexeko_super_admin role
        $user = $this->createAuthUser(RoleDefaults::HEXEKO_SUPER_ADMIN, returnDetails: true);
        $response = $this->actingAs($user)->getJson(self::URI);

        $response->assertStatus(200);

        // Hexeko super admin role should see all financers in the system
        $responseData = $response->json('data');
        $this->assertIsArray($responseData);

        // Verify our created financers are included
        $ids = collect($responseData)->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertContains($financer3->id, $ids);
        $this->assertContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_only_division_financers_for_division_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with division_admin role attached to financer1
        $user = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN, returnDetails: true);
        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Set authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer1->id],
            [$division1->id],
            [],
            $financer1->id
        );

        // Set context for accessible divisions
        Context::add('accessible_divisions', [$division1->id]);
        Context::add('financer_id', $financer1->id);

        $response = $this->actingAs($user)
            ->get(self::URI.'?division_id='.$division1->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Verify only division1 financers are returned
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertNotContains($financer3->id, $ids);
        $this->assertNotContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_only_division_financers_for_division_super_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with division_super_admin role attached to financer1
        $user = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN, returnDetails: true);

        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Set authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer1->id],
            [$division1->id],
            [],
            $financer1->id
        );

        // Set context for accessible divisions
        Context::add('accessible_divisions', [$division1->id]);
        Context::add('financer_id', $financer1->id);

        $response = $this->actingAs($user)
            ->get(self::URI.'?division_id='.$division1->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Verify only division1 financers are returned
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertNotContains($financer3->id, $ids);
        $this->assertNotContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_only_division_financers_for_financer_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with financer_admin role attached to financer1
        $user = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, returnDetails: true);

        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Set authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer1->id],
            [$division1->id],
            [],
            $financer1->id
        );

        // Set context for accessible divisions
        Context::add('accessible_divisions', [$division1->id]);
        Context::add('financer_id', $financer1->id);

        $response = $this->actingAs($user)
            ->get(self::URI.'?division_id='.$division1->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Verify only division1 financers are returned
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertNotContains($financer3->id, $ids);
        $this->assertNotContains($financer4->id, $ids);
    }

    #[Test]
    public function it_shows_only_division_financers_for_financer_super_admin_role(): void
    {
        // Create 2 divisions with 2 financers each
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);

        // Create user with financer_super_admin role attached to financer1
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN, returnDetails: true);

        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Set authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer1->id],
            [$division1->id],
            [],
            $financer1->id
        );

        // Set context for accessible divisions
        Context::add('accessible_divisions', [$division1->id]);
        Context::add('financer_id', $financer1->id);

        $response = $this->actingAs($user)
            ->get(self::URI.'?division_id='.$division1->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Verify only division1 financers are returned
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertNotContains($financer3->id, $ids);
        $this->assertNotContains($financer4->id, $ids);
    }

    #[Test]
    public function it_returns_error_for_user_without_query_params(): void
    {
        // Create financers
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        ModelFactory::createFinancer(['division_id' => $division->id]);

        // Create user with division_admin role with financer attachment
        $user = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN, returnDetails: true);

        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Make request WITHOUT division_id or financer_id query params
        $response = $this->actingAs($user)
            ->get(self::URI);

        $response->assertStatus(422);
    }

    #[Test]
    public function it_filters_by_user_divisions_when_multiple_divisions(): void
    {
        // Create 3 divisions with financers
        $division1 = ModelFactory::createDivision();
        $division2 = ModelFactory::createDivision();
        $division3 = ModelFactory::createDivision();

        $financer1 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division1->id]);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer4 = ModelFactory::createFinancer(['division_id' => $division2->id]);
        $financer5 = ModelFactory::createFinancer(['division_id' => $division3->id]);

        // Create user attached to financers from division1 and division2
        $user = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN, returnDetails: true);

        $user->financers()->attach($financer1->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->financers()->attach($financer3->id, ['active' => true, 'role' => RoleDefaults::BENEFICIARY]);
        $user->refresh();

        // Set authorization context
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer1->id, $financer3->id],
            [$division1->id, $division2->id],
            [],
            $financer1->id
        );

        // Set context for accessible divisions (both divisions since user has access to both)
        Context::add('accessible_divisions', [$division1->id, $division2->id]);
        Context::add('financer_id', $financer1->id);

        // With division_id query param, should only see division1 financers
        $response = $this->actingAs($user)
            ->get(self::URI.'?division_id='.$division1->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data');

        // Verify only division1 financers are returned
        $ids = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($financer1->id, $ids);
        $this->assertContains($financer2->id, $ids);
        $this->assertNotContains($financer3->id, $ids);
        $this->assertNotContains($financer4->id, $ids);
        $this->assertNotContains($financer5->id, $ids);
    }
}
