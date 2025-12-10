<?php

namespace Tests\Feature\Http\Controllers\V1\FinancerController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]

class FinancerOrderByTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    private Financer $mainFinancer;

    private Division $division;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a division first
        $this->division = Division::factory()->create();
        // Create a main financer for the tests in the same division
        $this->mainFinancer = Financer::factory()->create([
            'division_id' => $this->division->id,
        ]);

    }

    private function createAuthUserWithFinancer(): User
    {
        $user = $this->createAuthUser(RoleDefaults::DIVISION_SUPER_ADMIN);

        // Attach user to financer with active status
        $user->financers()->attach($this->mainFinancer->id, [
            'active' => true,
            'sirh_id' => 'TEST-'.$user->id,
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'division_super_admin',
        ]);

        Context::add('accessible_financers', $user->financers->pluck('id')->toArray());
        Context::add('accessible_divisions', [$this->division->id]);

        return $user;
    }

    private function createFinancersForSorting(): array
    {
        // Don't delete, just create the test financers
        $now = now();

        return [
            Financer::factory()->create([
                'name' => 'Alpha',
                'external_id' => ['ext' => 'A'],
                'created_at' => $now->copy()->subDays(4),
                'division_id' => $this->division->id,
            ]),
            Financer::factory()->create([
                'name' => 'Bravo',
                'external_id' => ['ext' => 'B'],
                'created_at' => $now->copy()->subDays(3),
                'division_id' => $this->division->id,
            ]),
            Financer::factory()->create([
                'name' => 'Charlie',
                'external_id' => ['ext' => 'C'],
                'created_at' => $now->copy()->subDays(2),
                'division_id' => $this->division->id,
            ]),
            Financer::factory()->create([
                'name' => 'Delta',
                'external_id' => ['ext' => 'D'],
                'created_at' => $now->copy()->subDay(),
                'division_id' => $this->division->id,
            ]),
            Financer::factory()->create([
                'name' => 'Echo',
                'external_id' => ['ext' => 'E'],
                'created_at' => $now,
                'division_id' => $this->division->id,
            ]),
        ];
    }

    #[Test]
    public function it_sorts_financers_by_name_ascending_with_order_by(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=name');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return isset($financer['external_id']['ext']) &&
                       in_array($financer['external_id']['ext'], ['A', 'B', 'C', 'D', 'E']);
            })
            ->values();
        $names = $financers->pluck('name')->all();
        $this->assertEquals(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'], $names);
    }

    #[Test]
    public function it_sorts_financers_by_external_id_ascending_with_order_by(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=external_id');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return isset($financer['external_id']['ext']) &&
                       in_array($financer['external_id']['ext'], ['A', 'B', 'C', 'D', 'E']);
            })
            ->values();
        $externalIds = $financers->pluck('external_id')->map(fn ($id) => $id['ext'])->all();
        $this->assertEquals(['A', 'B', 'C', 'D', 'E'], $externalIds);
    }

    #[Test]
    public function it_sorts_financers_by_created_at_ascending_with_order_by(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=created_at');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return in_array($financer['name'], ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']);
            })
            ->values();
        $names = $financers->pluck('name')->all();
        $this->assertEquals(['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo'], $names);
    }

    #[Test]
    public function it_sorts_financers_by_name_descending_with_order_by_desc(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by-desc=name');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->whereIn('external_id', [
                ['ext' => 'A'],
                ['ext' => 'B'],
                ['ext' => 'C'],
                ['ext' => 'D'],
                ['ext' => 'E'],
            ])
            ->values();
        $names = $financers->pluck('name')->all();
        $this->assertEquals(['Echo', 'Delta', 'Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_sorts_financers_by_external_id_descending_with_order_by_desc(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by-desc=external_id');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return isset($financer['external_id']['ext']) &&
                       in_array($financer['external_id']['ext'], ['A', 'B', 'C', 'D', 'E']);
            })
            ->values();
        $externalIds = $financers->pluck('external_id')->map(fn ($id) => $id['ext'])->all();
        $this->assertEquals(['E', 'D', 'C', 'B', 'A'], $externalIds);
    }

    #[Test]
    public function it_sorts_financers_by_created_at_descending_with_order_by_desc(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by-desc=created_at');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return in_array($financer['name'], ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']);
            })
            ->values();
        $names = $financers->pluck('name')->all();
        $this->assertEquals(['Echo', 'Delta', 'Charlie', 'Bravo', 'Alpha'], $names);
    }

    #[Test]
    public function it_returns_422_when_field_is_not_sortable(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=not_a_field');
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid sort field: not_a_field']);
    }

    #[Test]
    public function it_uses_default_sort_when_no_order_by_param(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return in_array($financer['name'], ['Alpha', 'Bravo', 'Charlie', 'Delta', 'Echo']);
            })
            ->values();
        $names = $financers->pluck('name')->all();
        $this->assertEquals(['Echo', 'Delta', 'Charlie', 'Bravo', 'Alpha'], $names); // default: created_at desc
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=name&order-by-desc=external_id');
        $response->assertOk();

        $financers = collect($response->json('data'))
            ->filter(function (array $financer): bool {
                return isset($financer['external_id']['ext']) &&
                       in_array($financer['external_id']['ext'], ['A', 'B', 'C', 'D', 'E']);
            })
            ->values();
        $externalIds = $financers->pluck('external_id')->map(fn ($id) => $id['ext'])->all();
        $this->assertEquals(['E', 'D', 'C', 'B', 'A'], $externalIds);
    }

    #[Test]
    public function it_rejects_sorting_on_fields_not_in_sortable(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $this->createFinancersForSorting();

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers?division_id='.$this->division->id.'&per_page=100&order-by=email');
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid sort field: email']);
    }
}
