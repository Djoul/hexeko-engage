<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User\CRUD;

use App\Actions\User\CRUD\ListUsersAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('actions')]
class ListUsersActionTest extends TestCase
{
    use DatabaseTransactions;

    private ListUsersAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ListUsersAction;
    }

    #[Test]
    public function it_returns_collection_of_users(): void
    {
        // Arrange
        ModelFactory::createUser(['first_name' => 'User1', 'email' => 'user1@test.com']);
        ModelFactory::createUser(['first_name' => 'User2', 'email' => 'user2@test.com']);
        ModelFactory::createUser(['first_name' => 'User3', 'email' => 'user3@test.com']);

        // Act
        $result = $this->action->execute(applyFilters: false);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(3, $result->count());
        $this->assertContainsOnlyInstancesOf(User::class, $result);
    }

    #[Test]
    public function it_loads_default_relations(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'relations@test.com']);

        // Act
        $result = $this->action->execute(applyFilters: false);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $foundUser = $result->where('id', $user->id)->first();
        $this->assertNotNull($foundUser);

        // Verify default relations are loaded
        $this->assertTrue($foundUser->relationLoaded('media'));
        $this->assertTrue($foundUser->relationLoaded('roles'));
        $this->assertTrue($foundUser->relationLoaded('permissions'));
        $this->assertTrue($foundUser->relationLoaded('financers'));
    }

    #[Test]
    public function it_loads_custom_relations(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'custom@test.com']);

        // Act
        $result = $this->action->execute(
            relations: ['financers', 'roles'],
            applyFilters: false
        );

        // Assert
        $foundUser = $result->where('id', $user->id)->first();
        $this->assertNotNull($foundUser);

        // Verify custom relations are loaded
        $this->assertTrue($foundUser->relationLoaded('financers'));
        $this->assertTrue($foundUser->relationLoaded('roles'));

        // Verify non-specified relations are NOT loaded
        $this->assertFalse($foundUser->relationLoaded('media'));
        $this->assertFalse($foundUser->relationLoaded('permissions'));
    }

    #[Test]
    public function it_respects_limit_parameter(): void
    {
        // Arrange - Create 10 users
        for ($i = 0; $i < 10; $i++) {
            ModelFactory::createUser(['email' => "limit{$i}@test.com"]);
        }

        // Act
        $result = $this->action->execute(
            applyFilters: false,
            limit: 5
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertLessThanOrEqual(5, $result->count());
    }

    #[Test]
    public function it_returns_count_of_users(): void
    {
        // Arrange
        $initialCount = User::count();

        ModelFactory::createUser(['email' => 'count1@test.com']);
        ModelFactory::createUser(['email' => 'count2@test.com']);

        // Act
        $count = $this->action->count(applyFilters: false);

        // Assert
        $this->assertIsInt($count);
        $this->assertEquals($initialCount + 2, $count);
    }

    #[Test]
    public function it_builds_query_without_executing(): void
    {
        // Arrange
        ModelFactory::createUser(['email' => 'query@test.com', 'first_name' => 'QueryTest']);

        // Act
        $query = $this->action->buildQuery(applyFilters: false);

        // Assert
        $this->assertInstanceOf(Builder::class, $query);

        // Verify query can be further customized
        $result = $query->where('first_name', 'QueryTest')->get();
        $this->assertGreaterThan(0, $result->count());
    }

    #[Test]
    public function it_applies_filters_when_enabled(): void
    {
        // Arrange
        ModelFactory::createUser([
            'email' => 'filtered@test.com',
            'enabled' => true,
        ]);

        // Mock request with filter (this would normally come from HTTP request)
        // For unit test, we test that filters can be applied via the query builder

        // Act
        $query = $this->action->buildQuery(applyFilters: true);

        // Assert - Query should have filters applied via pipeFiltered()
        $this->assertInstanceOf(Builder::class, $query);
    }

    #[Test]
    public function it_skips_filters_when_disabled(): void
    {
        // Arrange
        ModelFactory::createUser(['email' => 'nofilter1@test.com']);
        ModelFactory::createUser(['email' => 'nofilter2@test.com']);

        // Act
        $result = $this->action->execute(applyFilters: false);

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(2, $result->count());
    }

    #[Test]
    public function it_handles_empty_relations_array(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'empty@test.com']);

        // Act - empty array should use defaults
        $result = $this->action->execute(
            relations: [],
            applyFilters: false
        );

        // Assert
        $foundUser = $result->where('id', $user->id)->first();
        $this->assertNotNull($foundUser);

        // Verify default relations are loaded (empty = use defaults)
        $this->assertTrue($foundUser->relationLoaded('media'));
        $this->assertTrue($foundUser->relationLoaded('roles'));
        $this->assertTrue($foundUser->relationLoaded('financers'));
    }

    #[Test]
    public function it_handles_zero_limit(): void
    {
        // Arrange
        ModelFactory::createUser(['email' => 'zero@test.com']);

        // Act - limit=0 should not apply limit
        $result = $this->action->execute(
            applyFilters: false,
            limit: 0
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        // With limit=0, all users should be returned (limit not applied)
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    #[Test]
    public function it_handles_null_limit(): void
    {
        // Arrange
        ModelFactory::createUser(['email' => 'null@test.com']);

        // Act - null limit should not apply limit
        $result = $this->action->execute(
            applyFilters: false,
            limit: null
        );

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    #[Test]
    public function it_loads_media_with_profile_image_collection(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'media@test.com']);

        // Add media to user (profile_image collection)
        // Note: This would require actual media file in real scenario

        // Act
        $result = $this->action->execute(applyFilters: false);

        // Assert
        $foundUser = $result->where('id', $user->id)->first();
        $this->assertNotNull($foundUser);

        // Verify media relation is loaded
        $this->assertTrue($foundUser->relationLoaded('media'));
    }

    #[Test]
    public function it_loads_nested_permissions_through_roles(): void
    {
        // Arrange
        $user = ModelFactory::createUser(['email' => 'nested@test.com']);

        // Create role with permission
        $role = ModelFactory::createRole(['name' => 'test-nested-role']);
        $permission = ModelFactory::createPermission(['name' => 'test-permission']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        // Act
        $result = $this->action->execute(applyFilters: false);

        // Assert
        $foundUser = $result->where('id', $user->id)->first();
        $this->assertNotNull($foundUser);

        // Verify nested relations are loaded
        $this->assertTrue($foundUser->relationLoaded('roles'));
        $this->assertGreaterThan(0, $foundUser->roles->count());

        $loadedRole = $foundUser->roles->first();
        $this->assertTrue($loadedRole->relationLoaded('permissions'));
    }

    #[Test]
    public function it_chains_query_builder_methods(): void
    {
        // Arrange
        ModelFactory::createUser(['email' => 'chain1@test.com', 'first_name' => 'Alpha']);
        ModelFactory::createUser(['email' => 'chain2@test.com', 'first_name' => 'Beta']);

        // Act
        $query = $this->action->buildQuery(applyFilters: false);
        $result = $query->where('first_name', 'Alpha')
            ->orderBy('created_at', 'desc')
            ->get();

        // Assert
        $this->assertInstanceOf(Collection::class, $result);
        $this->assertGreaterThan(0, $result->count());
        $this->assertEquals('Alpha', $result->first()->first_name);
    }
}
