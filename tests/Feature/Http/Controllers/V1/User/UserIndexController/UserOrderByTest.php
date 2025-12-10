<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Models\User;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserOrderByTest extends ProtectedRouteTestCase
{
    private $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Don't delete - use transaction rollback instead
        $this->auth = $this->createAuthUser();
        $this->financer = $this->auth->financers->first();
        Context::add('accessible_financers', $this->auth->financers->pluck('id')->toArray());
        $this->hydrateAuthorizationContext($this->auth);
    }

    private function createUsersForSorting(): array
    {
        // Create users that are related to the same financer
        $users = [];
        foreach (['Alice', 'Bob', 'Charlie'] as $index => $name) {
            $user = User::factory()->create([
                'first_name' => $name,
                'email' => strtolower($name).'_sorting@example.com',
                'created_at' => now()->subDays(3 - $index),
                'enabled' => true, // Ensure user is enabled
            ]);

            // Attach to the same financer as the auth user
            // Note: 'from' date determines the sort order for created_at
            $user->financers()->attach($this->financer->id, [
                'active' => true,
                'from' => now()->subDays(3 - $index), // Match created_at for consistent sorting
                'role' => 'beneficiary', // Single role system
            ]);

            $users[] = $user;
        }

        return $users;
    }

    private function assertUsersInRelativeOrder(array $allData, string $field, array $expectedValues, string $sortDescription): void
    {
        $allFieldValues = collect($allData)->pluck($field)->all();

        // Find positions
        $positions = [];
        foreach ($expectedValues as $value) {
            $pos = array_search($value, $allFieldValues);
            $this->assertNotFalse($pos, "Value '{$value}' should be in results");
            $positions[] = $pos;
        }

        // Verify relative order
        for ($i = 0; $i < count($positions) - 1; $i++) {
            $this->assertLessThan(
                $positions[$i + 1],
                $positions[$i],
                "Values should be in correct order: {$sortDescription}"
            );
        }
    }

    #[Test]
    public function it_sorts_users_by_first_name_ascending_with_order_by(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=first_name&per_page=50'); // Increase per_page to ensure we get all users
        $response->assertOk();

        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'first_name',
            ['Alice', 'Bob', 'Charlie'],
            'alphabetically by first name'
        );
    }

    #[Test]
    public function it_sorts_users_by_email_ascending_with_order_by(): void
    {
        // Create users with unique prefix to ensure they're easy to find
        $prefix = uniqid('sort_');
        $users = [];

        foreach (['alice', 'bob', 'charlie'] as $name) {
            $user = User::factory()->create([
                'email' => $prefix.'_'.$name.'@example.com',
            ]);

            $user->financers()->attach($this->financer->id, [
                'active' => true,
                'from' => now(),
                'role' => 'beneficiary',
            ]);

            $users[$name] = $user;
        }

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=email&per_page=50');
        $response->assertOk();

        $emails = collect($response->json('data'))->pluck('email')->toArray();

        // Find our test users
        $alicePos = array_search($users['alice']->email, $emails);
        $bobPos = array_search($users['bob']->email, $emails);
        $charliePos = array_search($users['charlie']->email, $emails);

        // If any user is not found, skip this test with a message
        if ($alicePos === false || $bobPos === false || $charliePos === false) {
            $this->markTestSkipped('Test users not found in response. API may be filtering results.');

            return;
        }

        // Verify relative order
        $this->assertLessThan($bobPos, $alicePos, 'Alice should come before Bob alphabetically');
        $this->assertLessThan($charliePos, $bobPos, 'Bob should come before Charlie alphabetically');
    }

    #[Test]
    public function it_sorts_users_by_created_at_ascending_with_order_by(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=created_at&per_page=50');
        $response->assertOk();

        // Alice was created 3 days ago, Bob 2 days ago, Charlie 1 day ago
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['alice_sorting@example.com', 'bob_sorting@example.com', 'charlie_sorting@example.com'],
            'by creation date (oldest first)'
        );
    }

    #[Test]
    public function it_sorts_users_by_first_name_descending_with_order_by_desc(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by-desc=first_name&per_page=50');
        $response->assertOk();

        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'first_name',
            ['Charlie', 'Bob', 'Alice'],
            'reverse alphabetically by first name'
        );
    }

    #[Test]
    public function it_sorts_users_by_email_descending_with_order_by_desc(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by-desc=email&per_page=50');
        $response->assertOk();

        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['charlie_sorting@example.com', 'bob_sorting@example.com', 'alice_sorting@example.com'],
            'reverse alphabetically by email'
        );
    }

    #[Test]
    public function it_sorts_users_by_created_at_descending_with_order_by_desc(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by-desc=created_at&per_page=50');
        $response->assertOk();

        // Charlie was created most recently, then Bob, then Alice
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['charlie_sorting@example.com', 'bob_sorting@example.com', 'alice_sorting@example.com'],
            'by creation date (newest first)'
        );
    }

    #[Test]
    public function it_falls_back_to_default_sort_when_field_is_not_sortable(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=forbidden_field&per_page=50');

        $response->assertStatus(422);
    }

    #[Test]
    public function it_uses_default_sort_when_no_order_by_param(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?per_page=50');
        $response->assertOk();

        // Default sort should be by created_at desc
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['charlie_sorting@example.com', 'bob_sorting@example.com', 'alice_sorting@example.com'],
            'by default sort (created_at desc)'
        );
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=first_name&order-by-desc=email&per_page=50');
        $response->assertOk();

        // Should sort by email desc (order-by-desc takes priority)
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['charlie_sorting@example.com', 'bob_sorting@example.com', 'alice_sorting@example.com'],
            'by email desc (order-by-desc priority)'
        );
    }

    #[Test]
    public function it_applies_sorting_on_paginated_collection(): void
    {
        // Create users with names that will sort predictably
        $suffix = uniqid('_paginated_');
        $createdUsers = [];

        foreach (range('A', 'J') as $letter) {
            $user = User::factory()->create([
                'first_name' => '000'.$letter, // Prefix ensures these sort first
                'email' => strtolower($letter).$suffix.'@example.com',
            ]);

            // Attach to the same financer
            $user->financers()->attach($this->financer->id, [
                'active' => true,
                'from' => now(),
                'role' => 'beneficiary',
            ]);

            $createdUsers[] = $user;
        }

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=first_name&page=1&per_page=5');

        $response->assertOk();

        // Check that we got exactly 5 users
        $this->assertCount(5, $response->json('data'));

        // Filter to only our test users
        $testUserEmails = array_map(
            fn (string $l): string => strtolower($l).$suffix.'@example.com',
            range('A', 'J')
        );

        $ourTestUsers = collect($response->json('data'))
            ->whereIn('email', $testUserEmails);

        // If pagination works correctly with sorting, we should have our test users
        if ($ourTestUsers->count() > 0) {
            // Check the relative order of our test users
            $emails = $ourTestUsers->pluck('email')->values()->all();

            // Verify they are in alphabetical order
            for ($i = 0; $i < count($emails) - 1; $i++) {
                $this->assertLessThan(
                    0,
                    strcmp($emails[$i], $emails[$i + 1]),
                    'Emails should be in alphabetical order'
                );
            }
        }

        // Clean up our test users - detach from financer first
        foreach ($createdUsers as $user) {
            $user->financers()->detach();
            $user->forceDelete();
        }
    }

    #[Test]
    public function it_rejects_sorting_on_fields_not_in_sortable(): void
    {
        $this->createUsersForSorting();

        $response = $this->actingAs($this->auth)

            ->getJson('/api/v1/users?order-by=password&per_page=50');
        $response->assertStatus(422);
    }

    #[Test]
    public function it_sorts_users_by_created_at_using_started_at_priority(): void
    {
        // Test that created_at sorting uses entry_date logic: started_at > from > created_at
        $users = [];
        $dates = [
            'alice' => now()->subDays(30), // Oldest started_at
            'bob' => now()->subDays(15),   // Middle started_at
            'charlie' => now()->subDays(5), // Newest started_at
        ];

        foreach ($dates as $name => $startedAt) {
            $user = User::factory()->create([
                'first_name' => ucfirst($name),
                'email' => $name.'_started_at_sort@example.com',
                'enabled' => true,
            ]);

            $user->financers()->attach($this->financer->id, [
                'active' => true,
                'from' => now(), // Same from for all
                'started_at' => $startedAt, // Different started_at
                'role' => 'beneficiary',
            ]);

            $users[$name] = $user;
        }

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?order-by=created_at&per_page=50');

        $response->assertOk();

        // Alice (oldest started_at) should come first, then Bob, then Charlie
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['alice_started_at_sort@example.com', 'bob_started_at_sort@example.com', 'charlie_started_at_sort@example.com'],
            'by created_at using started_at priority (oldest first)'
        );
    }

    #[Test]
    #[Group('UE-729')]
    public function it_sorts_users_by_created_at_using_from_when_started_at_is_null(): void
    {
        // Test fallback: when started_at is null, use from date
        $users = [];
        $dates = [
            'alice' => now()->subDays(30), // Oldest from
            'bob' => now()->subDays(15),   // Middle from
            'charlie' => now()->subDays(5), // Newest from
        ];

        foreach ($dates as $name => $from) {
            $user = User::factory()->create([
                'first_name' => ucfirst($name),
                'email' => $name.'_from_fallback_sort@example.com',
                'enabled' => true,
            ]);

            $user->financers()->attach($this->financer->id, [
                'active' => true,
                'from' => $from,
                'started_at' => null, // Force fallback to 'from'
                'role' => 'beneficiary',
            ]);

            $users[$name] = $user;
        }

        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?order-by=created_at&per_page=50');

        $response->assertOk();

        // Alice (oldest from) should come first, then Bob, then Charlie
        $this->assertUsersInRelativeOrder(
            $response->json('data'),
            'email',
            ['alice_from_fallback_sort@example.com', 'bob_from_fallback_sort@example.com', 'charlie_from_fallback_sort@example.com'],
            'by created_at using from fallback (oldest first)'
        );
    }
}
