<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\DivisionController;

use App;
use App\Models\Division;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('division')]

class DivisionOrderByTest extends ProtectedRouteTestCase
{
    private array $testDivisionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Store initial count
        Division::count();

        // Create test divisions
        $alpha = Division::factory()->create([
            'name' => 'Alpha',
            'country' => 'FR',
            'currency' => 'EUR',
            'timezone' => 'Europe/Paris',
            'language' => App::currentLocale(),
            'created_at' => now()->subDays(2),
        ]);
        $bravo = Division::factory()->create([
            'name' => 'Bravo',
            'country' => 'DE',
            'currency' => 'EUR',
            'timezone' => 'Europe/Berlin',
            'language' => 'de',
            'created_at' => now()->subDays(1),
        ]);
        $charlie = Division::factory()->create([
            'name' => 'Charlie',
            'country' => 'US',
            'currency' => 'USD',
            'timezone' => 'America/New_York',
            'language' => 'en',
            'created_at' => now(),
        ]);

        // Store test division IDs
        $this->testDivisionIds = [
            $alpha->id,
            $bravo->id,
            $charlie->id,
        ];
    }

    #[Test]
    public function it_sorts_by_name_ascending(): void
    {
        $response = $this->getDivisions(['order-by' => 'name']);
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    #[Test]
    public function it_sorts_by_name_descending(): void
    {
        $response = $this->getDivisions(['order-by-desc' => 'name']);
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_sorts_by_created_at_ascending(): void
    {
        $response = $this->getDivisions(['order-by' => 'created_at']);
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $names);
    }

    #[Test]
    public function it_sorts_by_created_at_descending(): void
    {
        $response = $this->getDivisions(['order-by-desc' => 'created_at']);
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        $response = $this->getDivisions(['order-by' => 'name', 'order-by-desc' => 'created_at']);
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_returns_422_when_invalid_field_provided(): void
    {
        $response = $this->getDivisions(['order-by' => 'invalid_field']);
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid sort field: invalid_field']);
    }

    #[Test]
    public function it_falls_back_to_default_sorting_when_no_params(): void
    {
        $response = $this->getDivisions();
        $response->assertOk();

        // Filter only test divisions
        $divisions = collect($response->json('data'))
            ->filter(fn ($division): bool => in_array($division['id'], $this->testDivisionIds))
            ->values();

        $names = $divisions->pluck('name')->toArray();

        // Default is created_at desc
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $names);
    }

    private function getDivisions(array $params = []): TestResponse
    {
        return $this->getJson(route('divisions.index', $params));
    }
}
