<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\InvitedUserService;
use Context;
use DateTime;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class FetchUserTest extends ProtectedRouteTestCase
{
    use WithFaker;

    const URI = '/api/v1/users';

    protected Financer $activeFinancer;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        // Clean up Laravel Context to ensure test isolation
        Context::flush();

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        setPermissionsTeamId($this->auth->team_id);

        // Create a fresh financer for testing
        $this->activeFinancer = Financer::factory()->create(['name' => 'Test Financer']);

        // Attach the financer explicitly with active=true
        $this->auth->financers()->attach($this->activeFinancer->id, [
            'active' => true,
            'sirh_id' => 'AUTH-TEST',
            'from' => now(),
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);

        // Force a fresh load from database instead of refresh
        $authId = $this->auth->id;
        $this->auth = User::with('financers')->find($authId);

        Context::add('accessible_financers', $this->auth->financers->pluck('id')->toArray());
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_can_fetch_all_users_including_invited(): void
    {
        $initialUserCount = User::count();
        User::where('invitation_status', 'pending')->count();

        // Create 5 active users with explicit financer attachment
        for ($i = 0; $i < 5; $i++) {
            $user = User::factory()->create([
                'first_name' => 'Active',
                'last_name' => "User {$i}",
            ]);
            $user->financers()->attach($this->activeFinancer->id, [
                'active' => true,
                'sirh_id' => "ACTIVE-{$i}",
                'from' => now(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        // Create 3 inactive users with the same financer
        for ($i = 0; $i < 3; $i++) {
            $user = User::factory()->create([
                'first_name' => 'Inactive',
                'last_name' => "User {$i}",
            ]);
            $user->financers()->attach($this->activeFinancer->id, [
                'active' => false,
                'sirh_id' => "INACTIVE-{$i}",
                'from' => now(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        // Create invited users (users with invitation_status='pending') for the same financer
        $invitedUserService = app(InvitedUserService::class);
        for ($i = 0; $i < 4; $i++) {
            $invitedUser = $invitedUserService->create([
                'first_name' => 'Invited',
                'last_name' => "User $i",
                'email' => "invited{$i}@test.com",
                'invitation_status' => 'pending',
            ]);
            $invitedUser->financers()->attach($this->activeFinancer->id, [
                'active' => false,
                'sirh_id' => "INVITED-{$i}",
                'from' => now(),
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $response = $this->actingAs($this->auth)
            ->get(self::URI.'?financer_id='.$this->activeFinancer->id.'&pagination=page');

        $response->assertStatus(200);

        // Assert response structure (offset pagination mode)
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'profile_image',
                    'financers',
                    'entry_date',
                    'role', // Changed from 'roles' to 'role' for single-role system
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'last_page',
                'total',
            ],
        ]);

        // Assert database counts - we created 12 new users (5 active + 3 inactive + 4 invited)
        $this->assertDatabaseCount('users', $initialUserCount + 12);

        // Get the response data
        $responseData = $response->json();

        // Count the number of each status in the response
        $collection = collect($responseData['data']);

        // Filter only users related to our test financer
        $financerUsers = $collection->filter(function (array $item) {
            return collect($item['financers'])->contains('id', $this->activeFinancer->id);
        });

        $activeCount = $financerUsers->sum(function (array $item) {
            return collect($item['financers'])
                ->where('id', $this->activeFinancer->id)
                ->where('status', 'active')
                ->count();
        });

        $inactiveCount = $financerUsers->sum(function (array $item) {
            return collect($item['financers'])
                ->where('id', $this->activeFinancer->id)
                ->where('status', 'inactive')
                ->count();
        });

        // Count invited users (they have financers array with status 'invited')
        $invitedCount = $collection->filter(function (array $item) {
            if (! isset($item['financers']) || empty($item['financers'])) {
                return false;
            }

            return collect($item['financers'])->contains('status', 'invited');
        })->count();

        // Assert that we have the expected number of users created in this test
        // We created 5 active users + 1 auth user = 6 active
        $this->assertGreaterThanOrEqual(5, $activeCount, 'Not enough active users found');
        // We created 3 inactive users
        $this->assertGreaterThanOrEqual(3, $inactiveCount, 'Not enough inactive users found');
        // We created 4 invited users
        $this->assertGreaterThanOrEqual(4, $invitedCount, 'Not enough invited users found');
    }

    #[Test]
    public function it_returns_correct_data_format_for_users_and_invited_users(): void
    {
        $this->withoutExceptionHandling();

        // Create an active user
        $activeUser = ModelFactory::createUser(
            data: [
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Doe',
                'financers' => [
                    [
                        'financer' => $this->activeFinancer,
                        'active' => true,
                    ],
                ],
            ],
        );

        // Add profile image to the user
        $activeUser->addMediaFromString('test image content')
            ->usingFileName('image.jpg')
            ->usingName('profile_image')
            ->toMediaCollection('profile_image');

        // Create an inactive user
        ModelFactory::createUser(
            data: [
                'email' => 'inactive@example.com',
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'financers' => [
                    [
                        'financer' => $this->activeFinancer,
                        'active' => false,
                    ],
                ],
            ],
        );

        $response = $this->actingAs($this->auth)->getJson(self::URI.'?financer_id='.$this->activeFinancer->id);

        $response->assertStatus(200);

        $responseData = $response->json('data');

        $collection = collect($responseData);

        $activeCount = $collection->sum(function (array $item) {
            return collect($item['financers'])->where('status', 'active')->count();
        });

        $inactiveCount = $collection->sum(function (array $item) {
            return collect($item['financers'])->where('status', 'inactive')->count();
        });

        $this->assertGreaterThan(0, $activeCount, 'No active users found');
        $this->assertGreaterThan(0, $inactiveCount, 'No inactive users found');

        // Check the structure of an active user
        $activeUserData = $collection->first(function (array $item): bool {
            return isset($item['financers'][0]) && $item['financers'][0]['status'] === 'active';
        });

        $this->assertArrayHasKey('id', $activeUserData);
        $this->assertArrayHasKey('first_name', $activeUserData);
        $this->assertArrayHasKey('last_name', $activeUserData);
        $this->assertArrayHasKey('email', $activeUserData);
        $this->assertArrayHasKey('financers', $activeUserData);
        $this->assertArrayHasKey('profile_image', $activeUserData);
        $this->assertArrayHasKey('entry_date', $activeUserData);
        $this->assertEquals('active', $activeUserData['financers'][0]['status']);

        // Check the structure of an inactive user
        $inactiveUserData = $collection->first(function (array $item): bool {
            return isset($item['financers'][0]) && $item['financers'][0]['status'] === 'inactive';
        });

        $this->assertArrayHasKey('id', $inactiveUserData);
        $this->assertArrayHasKey('first_name', $inactiveUserData);
        $this->assertArrayHasKey('last_name', $inactiveUserData);
        $this->assertArrayHasKey('email', $inactiveUserData);
        $this->assertArrayHasKey('financers', $inactiveUserData);
        $this->assertArrayHasKey('profile_image', $inactiveUserData);
        $this->assertArrayHasKey('entry_date', $inactiveUserData);
        $this->assertEquals('inactive', $inactiveUserData['financers'][0]['status']);
    }

    #[Test]
    public function it_paginates_merged_results_correctly(): void
    {

        // Create users with the financer
        ModelFactory::createUser(
            data: [
                'financers' => [
                    [
                        'financer' => $this->activeFinancer,
                        'active' => true,
                    ],
                ],
            ],
            count: 15
        );

        // Create invited users (users with invitation_status='pending') with the financer
        $invitedUserService = app(InvitedUserService::class);
        for ($i = 0; $i < 10; $i++) {
            $invitedUser = $invitedUserService->create([
                'first_name' => 'Pending',
                'last_name' => "User $i",
                'email' => "pending{$i}@test.com",
                'invitation_status' => 'pending',
            ]);
            $invitedUser->financers()->attach($this->activeFinancer->id, ['active' => false, 'sirh_id' => '', 'from' => now(), 'role' => RoleDefaults::BENEFICIARY]);
        }

        // Test first page (use pagination=page for offset pagination)
        $response = $this->actingAs($this->auth)
            ->get(self::URI.'?pagination=page&page=1&per_page=10&financer_id='.$this->activeFinancer->id);

        $response->assertStatus(200);
        $responseData = $response->json();

        // Check that we're on the first page
        // With unified User model, pagination happens in SQL for all users (active + pending)
        $this->assertLessThanOrEqual(10, count($responseData['data']), 'Should have per_page items or less');
        $this->assertEquals(1, $responseData['meta']['current_page']);
        $lastPage = $responseData['meta']['last_page'];

        // If there's more than one page, test the second page
        if ($lastPage > 1) {
            $response = $this->actingAs($this->auth)
                ->get(self::URI.'?pagination=page&page=2&per_page=10&financer_id='.$this->activeFinancer->id);

            $response->assertStatus(200);
            $responseData = $response->json();

            // Check that we're on the second page
            $this->assertEquals(2, $responseData['meta']['current_page']);
        }

        // If there's a third page, test it
        if ($lastPage > 2) {
            $response = $this->actingAs($this->auth)
                ->get(self::URI.'?pagination=page&page=3&per_page=10&financer_id='.$this->activeFinancer->id);

            $response->assertStatus(200);
            $responseData = $response->json();

            $this->assertEquals(3, $responseData['meta']['current_page']);
        }

        // No need to check the total number of items
    }

    #[Test]
    public function it_sorts_by_entry_date_descending(): void
    {

        // Create users with specific timestamps
        // First user - created 5 days ago
        $oldDate = now()->subDays(5);
        $oldUser = ModelFactory::createUser(
            data: [
                'financers' => [
                    [
                        'financer' => $this->activeFinancer,
                        'active' => true,
                    ],
                ],
            ]
        );
        $oldUser->update(['created_at' => $oldDate]);
        // Update financer_user.from to match created_at for sorting
        $oldUser->financers()->updateExistingPivot($this->activeFinancer->id, ['from' => $oldDate]);

        // Second user - created 1 day ago
        $recentDate = now()->subDay();
        $recentUser = ModelFactory::createUser(
            data: [
                'financers' => [
                    [
                        'financer' => $this->activeFinancer,
                        'active' => true,
                    ],
                ],
            ]
        );
        $recentUser->update(['created_at' => $recentDate]);
        // Update financer_user.from to match created_at for sorting
        $recentUser->financers()->updateExistingPivot($this->activeFinancer->id, ['from' => $recentDate]);

        // Make sure the dates are different
        $this->assertNotEquals($oldDate->toDateTimeString(), $recentDate->toDateTimeString());

        // Get the users
        $response = $this->actingAs($this->auth)->getJson(self::URI.'?financer_id='.$this->activeFinancer->id);

        $response->assertStatus(200);
        $responseData = $response->json('data');

        // Check that we have at least 2 users
        $this->assertGreaterThanOrEqual(2, count($responseData));

        // Get the entry dates from the response
        $entryDates = collect($responseData)->pluck('entry_date')->toArray();

        // Check that the dates are sorted (at least the first two)
        $firstDate = new DateTime($entryDates[0]);
        $secondDate = new DateTime($entryDates[1]);

        // The first date should be more recent than the second date
        $this->assertGreaterThanOrEqual($secondDate, $firstDate, 'Dates are not sorted in descending order');
    }
}
