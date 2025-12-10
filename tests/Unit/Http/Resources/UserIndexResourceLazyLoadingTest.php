<?php

namespace Tests\Unit\Http\Resources;

use App\Http\Resources\User\UserIndexResource;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('resources')]
#[Group('user')]
class UserIndexResourceLazyLoadingTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_handles_lazy_loading_properly_when_financers_relation_is_not_loaded(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach financer to user
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Get fresh user without relations
        $freshUser = User::find($user->id);

        // Enable query log
        DB::enableQueryLog();

        // Create resource
        $resource = new UserIndexResource($freshUser);

        // Convert to array (this should not throw lazy loading exception)
        $result = $resource->toArray(request());

        // Verify the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('financers', $result);

        // Verify no additional queries were made
        $queries = DB::getQueryLog();
        $financerQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'financers');
        });

        // Should have no queries to financers (UserIndexResource uses relationLoaded check)
        $this->assertCount(0, $financerQueries);
    }

    #[Test]
    public function it_includes_financers_when_relation_is_preloaded(): void
    {
        $user = User::factory()->create();
        $financer = Financer::factory()->create();

        // Attach financer to user
        $user->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Get user with financers relation
        $userWithRelation = User::with('financers')->find($user->id);

        // Create resource
        $resource = new UserIndexResource($userWithRelation);

        // Convert to array
        $result = $resource->toArray(request());

        // Verify the result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('financers', $result);

    }

    #[Test]
    public function it_returns_all_expected_fields_for_user(): void
    {
        // Create a user with the financer_admin role using the test helper
        $user = $this->createAuthUser('financer_admin');

        // Update user details
        $user->update([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',

        ]);

        // Create resource
        $resource = new UserIndexResource($user);

        // Convert to array
        $result = $resource->toArray(request());

        // Verify all expected fields are present - UserIndexResource only returns these fields
        $expectedFields = [
            'id', 'first_name', 'last_name', 'email',
            'financers', 'profile_image', 'entry_date', 'description',
        ];

        foreach ($expectedFields as $field) {
            $this->assertArrayHasKey($field, $result);
        }

        // Verify specific values
        $this->assertEquals('test@example.com', $result['email']);
        $this->assertEquals('John', $result['first_name']);
        $this->assertEquals('Doe', $result['last_name']);
        $this->assertNull($result['profile_image']); // No profile image set
        $this->assertNotNull($result['entry_date']);
    }
}
