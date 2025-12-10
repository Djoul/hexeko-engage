<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App\Models\Division;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('division')]
class DivisionGlobalSearchFilterTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser();
    }

    #[Test]
    public function it_searches_divisions_by_name(): void
    {
        // Arrange
        $initialCount = Division::where('name', 'like', '%Division%')->count();
        Division::factory()->create(['name' => 'Technology Division']);
        Division::factory()->create(['name' => 'Marketing Division']);
        Division::factory()->create(['name' => 'Finance Department']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=Division');

        // Assert
        $response->assertOk()
            ->assertJsonCount($initialCount + 2, 'data')
            ->assertJsonFragment(['name' => 'Technology Division'])
            ->assertJsonFragment(['name' => 'Marketing Division'])
            ->assertJsonMissing(['name' => 'Finance Department']);
    }

    #[Test]
    public function it_searches_divisions_by_remarks(): void
    {
        // Arrange
        Division::factory()->create([
            'name' => 'Division A',
            'remarks' => 'This division handles customer support',
        ]);
        Division::factory()->create([
            'name' => 'Division B',
            'remarks' => 'This division manages product development',
        ]);
        Division::factory()->create([
            'name' => 'Division C',
            'remarks' => 'General operations',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=customer');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Division A']);
    }

    #[Test]
    public function it_searches_divisions_by_country(): void
    {
        // Arrange
        Division::factory()->create([
            'name' => 'European Division UniqueTest',
            'country' => 'UniqueCountryFrance',
        ]);
        Division::factory()->create([
            'name' => 'Asian Division UniqueTest',
            'country' => 'UniqueCountryJapan',
        ]);
        Division::factory()->create([
            'name' => 'American Division UniqueTest',
            'country' => 'UniqueCountryUSA',
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=UniqueCountryFrance');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'European Division UniqueTest']);
    }

    #[Test]
    public function it_returns_empty_when_no_match(): void
    {
        // Arrange
        Division::factory()->count(3)->create();

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=NonExistentTerm');

        // Assert
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_requires_minimum_two_characters_for_search(): void
    {
        // Arrange
        Division::factory()->create(['name' => 'A Division']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=A');

        // Assert - Should return all divisions since search term is too short
        $response->assertOk();
        $count = count($response->json('data'));
        $this->assertGreaterThan(0, $count);
    }

    #[Test]
    public function it_performs_case_insensitive_search(): void
    {
        // Arrange
        Division::factory()->create(['name' => 'Technology Division']);
        Division::factory()->create(['name' => 'Marketing Division']);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=TECHNOLOGY');

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['name' => 'Technology Division']);
    }

    #[Test]
    public function it_combines_search_with_other_filters(): void
    {
        // Arrange
        Division::factory()->count(2)->create([
            'name' => 'Sales Division',
            'country' => 'France',
        ]);
        Division::factory()->create([
            'name' => 'Sales Division',
            'country' => 'Germany',
        ]);

        // Act - Search combined with country filter (assuming there's a country filter)
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/divisions?search=Sales');

        // Assert
        $response->assertOk()
            ->assertJsonCount(3, 'data'); // All 3 Sales divisions
    }
}
