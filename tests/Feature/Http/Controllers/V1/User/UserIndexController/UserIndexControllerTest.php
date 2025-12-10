<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\User;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
final class UserIndexControllerTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with READ_USER permission
        $this->auth = $this->createAuthUser(RoleDefaults::GOD);

        // Set accessible financers context (required by FinancerIdFilter and StatusFilter)
        $financerIds = $this->auth->financers->pluck('id')->toArray();
        Context::add('accessible_financers', $financerIds);
        Context::add('financer_ids', $financerIds);

        // Set the active financer in Context for global scopes
        if (count($financerIds) > 0) {
            Context::add('financer_id', $financerIds[0]);
        }
    }

    // ==========================================
    // Contract Tests: Pagination
    // ==========================================

    #[Test]
    public function it_supports_offset_pagination_with_totals(): void
    {
        // Arrange: Create 100 users with financer relationships
        $financer = $this->auth->financers->first();
        for ($i = 0; $i < 100; $i++) {
            ModelFactory::createUser([
                'email' => "offset{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Act: Request page 2 with offset pagination
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?per_page=25&pagination=page&page=2');

        // Assert: Offset pagination metadata present
        $response->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => [
                    'current_page',
                    'per_page',
                    'last_page',
                    'total',
                    'assignable_roles',
                ],
            ])
            ->assertJson([
                'meta' => [
                    'current_page' => 2,
                    'per_page' => 25,
                ],
            ]);

        // Assert: Total and last_page calculated correctly
        $meta = $response->json('meta');
        $this->assertGreaterThan(100, $meta['total']); // Includes auth user
        $this->assertEquals(ceil($meta['total'] / 25), $meta['last_page']);
    }

    // ==========================================
    // Contract Tests: Filtering
    // ==========================================

    #[Test]
    public function it_searches_across_email_name_description(): void
    {
        // Arrange: Create user with specific searchable data and financer
        $financer = $this->auth->financers->first();
        $targetUser = ModelFactory::createUser([
            'email' => 'john.unique.email@test.com',
            'first_name' => 'John',
            'last_name' => 'UniqueLastName',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Create other users
        for ($i = 0; $i < 10; $i++) {
            ModelFactory::createUser([
                'email' => "other{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Act: Search by email fragment
        $responseEmail = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=unique.email');

        // Assert: Found by email
        $responseEmail->assertOk();
        $this->assertContains($targetUser->id, collect($responseEmail->json('data'))->pluck('id')->toArray());

        // Act: Search by last name
        $responseLastName = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=UniqueLastName');

        // Assert: Found by last name
        $responseLastName->assertOk();
        $this->assertContains($targetUser->id, collect($responseLastName->json('data'))->pluck('id')->toArray());
    }

    #[Test]
    public function it_combines_status_role_enabled_filters(): void
    {
        $financer = $this->auth->financers->first();

        // Create users using auth user's financer to ensure they're in accessible_financers context
        // Active, enabled user (target - should be included)
        $targetUser = ModelFactory::createUser([
            'email' => 'active@test.com',
            'enabled' => true,
            'financers' => [
                ['financer' => $financer, 'active' => true], // status=active
            ],
        ]);

        // User with enabled=false (should be filtered out by enabled=1 filter)
        $disabledUser = ModelFactory::createUser([
            'email' => 'disabled@test.com',
            'enabled' => false,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // User with inactive status (should be filtered out by status=active filter)
        $inactiveStatusUser = ModelFactory::createUser([
            'email' => 'inactive@test.com',
            'enabled' => true,
            'financers' => [
                ['financer' => $financer, 'active' => false], // status=inactive
            ],
        ]);

        // Act: Apply combined filters (status=active AND enabled=1)
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?status=active&enabled=1&per_page=50');

        // Assert: Only matching user returned
        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($targetUser->id, $returnedIds, 'Target user (active + enabled) should be included');
        $this->assertNotContains($disabledUser->id, $returnedIds, 'Disabled user should be filtered out');
        $this->assertNotContains($inactiveStatusUser->id, $returnedIds, 'Inactive status user should be filtered out');
    }

    // ==========================================
    // Performance Tests
    // ==========================================

    #[Test]
    public function it_consumes_less_than_64mb_per_request(): void
    {
        // Arrange: Create realistic dataset with financer relationships
        $financer = $this->auth->financers->first();
        for ($i = 0; $i < 100; $i++) {
            ModelFactory::createUser([
                'email' => "memory{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Act: Measure memory consumption
        $memoryBefore = memory_get_peak_usage(true);

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?per_page=25');

        $memoryAfter = memory_get_peak_usage(true);
        $memoryUsed = $memoryAfter - $memoryBefore;

        // Assert: Response successful and memory under threshold
        $response->assertOk();

        $memoryMB = $memoryUsed / (1024 * 1024);
        $this->assertLessThan(64, $memoryMB, "Memory consumption: {$memoryMB}MB exceeds 64MB limit");
    }

    #[Test]
    public function it_responds_in_less_than_800ms_p95(): void
    {
        // Arrange: Create dataset with financer relationships
        $financer = $this->auth->financers->first();
        for ($i = 0; $i < 100; $i++) {
            ModelFactory::createUser([
                'email' => "perf{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Act: Measure response times over multiple requests
        $responseTimes = [];
        $iterations = 20; // Reduced for test performance

        for ($i = 0; $i < $iterations; $i++) {
            $start = microtime(true);

            $this->actingAs($this->auth)
                ->getJson('/api/v1/users?per_page=25');

            $duration = (microtime(true) - $start) * 1000; // Convert to milliseconds
            $responseTimes[] = $duration;
        }

        // Calculate P95
        sort($responseTimes);
        $p95Index = (int) ceil(0.95 * count($responseTimes)) - 1;
        $p95Time = $responseTimes[$p95Index];

        // Assert: P95 under 800ms
        $this->assertLessThan(800, $p95Time, "P95 response time: {$p95Time}ms exceeds 800ms limit");
    }

    #[Test]
    public function it_avoids_n_plus_one_queries(): void
    {
        // Arrange: Create users with relationships and financer
        $financer = $this->auth->financers->first();
        for ($i = 0; $i < 25; $i++) {
            ModelFactory::createUser([
                'email' => "query{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Enable query logging
        DB::connection()->enableQueryLog();

        // Act: Request user list
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?per_page=25');

        // Get query count
        $queries = DB::connection()->getQueryLog();
        $queryCount = count($queries);

        // Disable query logging
        DB::connection()->disableQueryLog();

        // Assert: Response successful
        $response->assertOk();

        // Assert: Query count under threshold (realistic for complex eager loading)
        // Main user query + media + roles + role.permissions + user.permissions + financers + Context check
        // Expected: ~7-10 queries with all eager loads (roles, permissions, financers, media)
        $this->assertLessThanOrEqual(10, $queryCount, "Query count: {$queryCount} exceeds N+1 threshold");
    }

    // ==========================================
    // Edge Case Tests
    // ==========================================

    #[Test]
    public function it_rejects_invalid_sort_fields(): void
    {
        // Act: Attempt to sort by invalid field
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?order-by=invalid_field');

        // Assert: Validation error returned (422 status with error message)
        $response->assertStatus(422);
        $this->assertStringContainsString('Invalid sort field', $response->json('message') ?? '');
    }

    #[Test]
    public function it_returns_empty_array_with_valid_meta_when_no_results(): void
    {
        // Arrange: Delete all users except auth user
        User::whereNot('id', $this->auth->id)->delete();

        // Act: Search for non-existent user
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=nonexistent-unique-search-term-12345');

        // Assert: Empty data array with valid metadata
        $response->assertOk()
            ->assertJson(['data' => []])
            ->assertJsonStructure([
                'data',
                'meta' => ['per_page', 'assignable_roles'],
            ]);
    }

    #[Test]
    public function it_enforces_per_page_min_max_constraints(): void
    {
        // Arrange: Create users with financer relationships
        $financer = $this->auth->financers->first();
        for ($i = 0; $i < 150; $i++) {
            ModelFactory::createUser([
                'email' => "constraint{$i}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true],
                ],
            ]);
        }

        // Act: Request with negative per_page (should normalize to 25)
        $responseNegative = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?per_page=-10');

        // Assert: Normalized to default (25)
        $responseNegative->assertOk()
            ->assertJsonCount(25, 'data');

        // Act: Request with per_page > 100 (should cap at 100)
        $responseMax = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?per_page=200');

        // Assert: Capped at max (100)
        $responseMax->assertOk()
            ->assertJsonCount(100, 'data');
    }

    // ==========================================
    // Security Tests: GOD Take Control Context
    // ==========================================

    #[Test]
    #[Group('security')]
    public function god_user_respects_authorization_context_when_viewing_users(): void
    {
        // Arrange: Create GOD user with 2 financers
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 1']);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 2']);

        $godUser = ModelFactory::createUser([
            'email' => 'god@test.com',
            'financers' => [
                ['financer' => $financer1, 'active' => true],
                ['financer' => $financer2, 'active' => true],
            ],
        ]);
        $godUser->assignRole(RoleDefaults::GOD);

        // Create target user with both financers
        $targetUser = ModelFactory::createUser([
            'email' => 'target@test.com',
            'financers' => [
                ['financer' => $financer1, 'active' => true],
                ['financer' => $financer2, 'active' => true],
            ],
        ]);

        // Simulate Take Control: GOD user context limited to financer1 only
        authorizationContext()->hydrate(
            AuthorizationMode::TAKE_CONTROL,
            [$financer1->id],
            [$division->id],
            [],
            $financer1->id
        );

        Context::add('accessible_financers', [$financer1->id]);
        Context::add('accessible_divisions', [$division->id]);
        Context::add('financer_id', $financer1->id);

        // Act: Fetch users as GOD with limited context
        $response = $this->actingAs($godUser)
            ->getJson('/api/v1/users');

        // Assert: GOD user sees users but financers are filtered by context
        $response->assertOk();

        $targetUserData = collect($response->json('data'))
            ->firstWhere('id', $targetUser->id);

        $this->assertNotNull($targetUserData, 'Target user should be visible');

        // Critical: Only financer1 should be visible, NOT financer2
        $financerIds = collect($targetUserData['financers'])->pluck('id')->toArray();
        $this->assertContains($financer1->id, $financerIds, 'Financer1 should be visible');
        $this->assertNotContains($financer2->id, $financerIds, 'Financer2 should NOT be visible (outside context)');
        $this->assertCount(1, $financerIds, 'Only 1 financer should be visible');
    }

    #[Test]
    #[Group('security')]
    public function requesting_unauthorized_financer_id_throws_validation_exception(): void
    {
        // Arrange: Create GOD user with access to financer1 only
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $godUser = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financer1, 'active' => true],
            ],
        ]);
        $godUser->assignRole(RoleDefaults::GOD);

        // Set context to financer1 only
        authorizationContext()->hydrate(
            AuthorizationMode::TAKE_CONTROL,
            [$financer1->id],
            [$division->id],
            [],
            $financer1->id
        );

        Context::add('accessible_financers', [$financer1->id]);
        Context::add('accessible_divisions', [$division->id]);
        Context::add('financer_id', $financer1->id);

        // Act: Try to filter by financer2 (unauthorized)
        $response = $this->actingAs($godUser)
            ->getJson("/api/v1/users?financer_id={$financer2->id}");

        // Assert: Should return 422 with validation error
        $response->assertStatus(422)
            ->assertJsonValidationErrors('financer_id');

        $errorMessage = $response->json('errors.financer_id.0');
        $this->assertStringContainsString('not accessible', $errorMessage);
    }

    #[Test]
    #[Group('security')]
    public function god_user_can_filter_by_multiple_financer_ids(): void
    {
        // Arrange: Create GOD user with 3 financers
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 1']);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 2']);
        $financer3 = ModelFactory::createFinancer(['division_id' => $division->id, 'name' => 'Financer 3']);

        $godUser = ModelFactory::createUser([
            'email' => 'god-multi@test.com',
            'financers' => [
                ['financer' => $financer1, 'active' => true],
                ['financer' => $financer2, 'active' => true],
                ['financer' => $financer3, 'active' => true],
            ],
        ]);
        $godUser->assignRole(RoleDefaults::GOD);

        // Create target users with different financers
        $userF1 = ModelFactory::createUser([
            'email' => 'user-f1@test.com',
            'financers' => [['financer' => $financer1, 'active' => true]],
        ]);
        $userF2 = ModelFactory::createUser([
            'email' => 'user-f2@test.com',
            'financers' => [['financer' => $financer2, 'active' => true]],
        ]);
        $userF3 = ModelFactory::createUser([
            'email' => 'user-f3@test.com',
            'financers' => [['financer' => $financer3, 'active' => true]],
        ]);

        // Simulate middleware setting context based on financer_id query param
        authorizationContext()->hydrate(
            AuthorizationMode::TAKE_CONTROL,
            [$financer1->id, $financer2->id],
            [$division->id],
            [],
            $financer1->id
        );

        Context::add('accessible_financers', [$financer1->id, $financer2->id]);
        Context::add('accessible_divisions', [$division->id]);
        Context::add('financer_id', $financer1->id);

        // Act: Filter by financer1 and financer2 (comma-separated)
        $response = $this->actingAs($godUser)
            ->getJson("/api/v1/users?financer_id={$financer1->id},{$financer2->id}");

        // Assert: Only users from financer1 and financer2 visible
        $response->assertOk();
        $returnedIds = collect($response->json('data'))->pluck('id')->toArray();

        $this->assertContains($userF1->id, $returnedIds, 'User from financer1 should be visible');
        $this->assertContains($userF2->id, $returnedIds, 'User from financer2 should be visible');
        $this->assertNotContains($userF3->id, $returnedIds, 'User from financer3 should NOT be visible');

        // Verify financers in user data are also limited
        $userData = collect($response->json('data'))->firstWhere('id', $godUser->id);
        if ($userData) {
            $financerIds = collect($userData['financers'])->pluck('id')->toArray();
            $this->assertCount(2, $financerIds, 'GOD user should only see 2 financers in context');
            $this->assertContains($financer1->id, $financerIds);
            $this->assertContains($financer2->id, $financerIds);
            $this->assertNotContains($financer3->id, $financerIds);
        }
    }
}
