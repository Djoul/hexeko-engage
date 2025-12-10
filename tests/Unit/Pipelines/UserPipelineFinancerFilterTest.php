<?php

namespace Tests\Unit\Pipelines;

use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\User;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserPipelineFinancerFilterTest extends ProtectedRouteTestCase
{
    public $user;

    use DatabaseTransactions;

    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->financer = ModelFactory::createFinancer();
        $this->user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer, 'active' => true],
            ],
        ]);
        $this->actingAs($this->user);

        // Manually hydrate authorization context with the exact financer we want
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id
        );

        // Set Context for global scopes
        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('accessible_divisions', [$this->financer->division_id]);
        Context::add('financer_id', $this->financer->id);
    }

    #[Test]
    public function it_filters_users_by_financer_id(): void
    {
        // Arrange
        $financer2 = ModelFactory::createFinancer();

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $user3 = User::factory()->create();

        // Associate users with financers
        $user1->financers()->attach($this->financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $user2->financers()->attach($financer2->id, ['role' => 'beneficiary', 'from' => now()]);
        $user3->financers()->attach($this->financer->id, ['role' => 'beneficiary', 'from' => now()]);

        request()->merge(['financer_id' => $this->financer->id]);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert
        $this->assertTrue($result->contains($user1));
        $this->assertFalse($result->contains($user2));
        $this->assertTrue($result->contains($user3));
    }

    #[Test]
    public function it_returns_users_with_direct_financer_relationship(): void
    {
        // Arrange

        // Create users with different financer relationships
        $userWithRelation = User::factory()->create();
        $userWithoutRelation = User::factory()->create();

        $userWithRelation->financers()->attach($this->financer->id, ['role' => 'beneficiary', 'from' => now()]);

        request()->merge(['financer_id' => $this->financer->id]);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert
        $this->assertTrue($result->contains($userWithRelation));
        $this->assertFalse($result->contains($userWithoutRelation));
    }

    #[Test]
    public function it_returns_empty_collection_when_filtering_by_non_existent_financer_id(): void
    {
        // Arrange
        $this->withoutExceptionHandling();
        $user = User::factory()->create();

        $user->financers()->attach($this->financer->id, ['role' => 'beneficiary', 'from' => now()]);

        $nonExistentFinancerId = '00000000-0000-0000-0000-000000000000';
        request()->merge(['financer_id' => $nonExistentFinancerId]);

        // Assert: Should throw ValidationException (security: unauthorized financer_id)
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('One or more financer IDs are not accessible to you.');

        // Act
        User::query()->pipeFiltered()->get();
    }

    #[Test]
    public function it_uses_active_financer_when_value_is_not_string(): void
    {
        // Arrange
        $financer1 = $this->financer;
        $financer2 = ModelFactory::createFinancer();

        $user1 = User::factory()->create();
        $user1->financers()->attach($financer1->id, ['active' => true, 'from' => now(), 'role' => 'beneficiary']);

        $user2 = User::factory()->create();
        $user2->financers()->attach($financer2->id, ['role' => 'beneficiary', 'from' => now()]);

        // Login as user1 and set the correct context
        $this->actingAs($user1);

        // Update context for user1 using helper
        $user1->refresh();
        $this->hydrateAuthorizationContext($user1);

        $financerIds = $user1->financers->pluck('id')->toArray();
        $divisionIds = $user1->financers->pluck('division_id')->toArray();

        Context::add('accessible_financers', $financerIds);
        Context::add('accessible_divisions', $divisionIds);
        Context::add('financer_id', $financer1->id);

        // Try with array value which should trigger activeFinancerID usage
        request()->merge(['financer_id' => ['invalid']]);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert - Should return only users from financer1 (active financer of logged user)
        $this->assertTrue($result->contains($user1));
        $this->assertFalse($result->contains($user2));
    }

    #[Test]
    public function it_combines_financer_filter_with_other_filters(): void
    {
        // Arrange
        $financer = $this->financer;

        $enabledUser = User::factory()->create(['enabled' => true]);
        $disabledUser = User::factory()->create(['enabled' => false]);
        $enabledUserOtherFinancer = User::factory()->create(['enabled' => true]);

        $enabledUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);
        $disabledUser->financers()->attach($financer->id, ['role' => 'beneficiary', 'from' => now()]);

        request()->merge([
            'financer_id' => $financer->id,
            'enabled' => 'true',
        ]);

        // Act
        $result = User::query()->pipeFiltered()->get();

        // Assert
        $this->assertTrue($result->contains($enabledUser));
        $this->assertFalse($result->contains($disabledUser));
        $this->assertFalse($result->contains($enabledUserOtherFinancer));
    }
}
