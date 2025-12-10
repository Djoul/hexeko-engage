<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserIndexController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use App\Models\Team;
use App\Models\User;
use Context;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserGlobalSearchFilterTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Create user with admin role to have full access to users
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Use the financer already attached to auth user
        $this->financer = $this->auth->financers->first();

        Context::add('accessible_financers', $this->auth->financers->pluck('id')->toArray());
    }

    private function createUserWithFinancer(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->financers()->attach($this->financer->id, ['role' => 'beneficiary', 'from' => now()]);

        return $user;
    }

    #[Test]
    public function it_searches_users_by_first_name(): void
    {
        // Arrange
        $this->createUserWithFinancer(['first_name' => 'John UniqueFirst2025']);
        $this->createUserWithFinancer(['first_name' => 'Jane']);
        $this->createUserWithFinancer(['first_name' => 'Bob']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=UniqueFirst2025&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('John UniqueFirst2025', $data[0]['first_name']);
    }

    #[Test]
    public function it_searches_users_by_last_name(): void
    {
        // Arrange
        $this->createUserWithFinancer(['last_name' => 'Smith UniqueLast2025']);
        $this->createUserWithFinancer(['last_name' => 'Johnson']);
        $this->createUserWithFinancer(['last_name' => 'Brown']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=UniqueLast2025&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Smith UniqueLast2025', $data[0]['last_name']);
    }

    #[Test]
    public function it_searches_users_by_email(): void
    {
        // Arrange
        $this->createUserWithFinancer(['email' => 'john.unique2025@example.com']);
        $this->createUserWithFinancer(['email' => 'jane@example.com']);
        $this->createUserWithFinancer(['email' => 'bob@example.com']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=unique2025@example&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('john.unique2025@example.com', $data[0]['email']);
    }

    #[Test]
    public function it_searches_users_by_full_name(): void
    {
        // Arrange - Test virtual field full_name using SQL CONCAT expression
        $this->createUserWithFinancer([
            'first_name' => 'Maxwell',
            'last_name' => 'Sterling',
            'email' => 'maxwell@example.com',
        ]);
        $this->createUserWithFinancer([
            'first_name' => 'Alice',
            'last_name' => 'Johnson',
            'email' => 'alice@example.com',
        ]);

        // Act - Search by full name (first + last name combined)
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=Maxwell Sterling&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Maxwell', $data[0]['first_name']);
        $this->assertEquals('Sterling', $data[0]['last_name']);
    }

    #[Test]
    public function it_searches_users_by_partial_full_name(): void
    {
        // Arrange
        $this->createUserWithFinancer([
            'first_name' => 'Leonardo',
            'last_name' => 'DiCaprio',
            'email' => 'leo@example.com',
        ]);
        $this->createUserWithFinancer([
            'first_name' => 'Brad',
            'last_name' => 'Pitt',
            'email' => 'brad@example.com',
        ]);

        // Act - Search by partial last name in full name context
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=DiCaprio&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Leonardo', $data[0]['first_name']);
        $this->assertEquals('DiCaprio', $data[0]['last_name']);
    }

    #[Test]
    public function it_searches_users_by_team_name(): void
    {
        // Arrange - This tests relation search
        $team1 = Team::factory()->create(['name' => 'Development Team UniqueTeam2025']);
        $team2 = Team::factory()->create(['name' => 'Marketing Team']);

        $this->createUserWithFinancer([
            'first_name' => 'Alice',
            'team_id' => $team1->id,
        ]);
        $this->createUserWithFinancer([
            'first_name' => 'Charlie',
            'team_id' => $team2->id,
        ]);

        // Act - Search by team name through relation
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=UniqueTeam2025&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Alice', $data[0]['first_name']);
    }

    #[Test]
    public function it_performs_case_insensitive_search(): void
    {
        // Arrange
        $this->createUserWithFinancer(['first_name' => 'UniqueTestName2025']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=UNIQUETESTNAME2025&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('UniqueTestName2025', $data[0]['first_name']);
    }

    #[Test]
    public function it_requires_minimum_two_characters_for_search(): void
    {
        // Arrange
        $this->createUserWithFinancer(['first_name' => 'A User']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=A&financer_id='.$this->financer->id);

        // Assert - Should return all users since search term is too short
        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
    }

    #[Test]
    public function it_returns_empty_when_no_match(): void
    {
        // Arrange
        $this->createUserWithFinancer();
        $this->createUserWithFinancer();
        $this->createUserWithFinancer();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=NonExistentUserUnique2025&financer_id='.$this->financer->id);

        // Assert
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(0, $data);
    }

    #[Test]
    public function it_combines_search_with_other_filters(): void
    {
        // Arrange
        $this->createUserWithFinancer([
            'first_name' => 'John SearchCombo2025',
            'enabled' => true,
        ]);
        $this->createUserWithFinancer([
            'first_name' => 'Jane SearchCombo2025',
            'enabled' => false,
        ]);

        // Act - Search with enabled filter
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/users?search=SearchCombo2025&enabled=1&financer_id='.$this->financer->id);

        // Assert - Should only return enabled user
        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('John SearchCombo2025', $data[0]['first_name']);
    }
}
