<?php

declare(strict_types=1);

namespace Tests\Unit\QueryFilters\ModelSpecific\Financer;

use App\Enums\FinancerStatus;
use App\Models\Division;
use App\Models\Financer;
use App\QueryFilters\ModelSpecific\Financer\StatusFilter;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group as TestGroup;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['financers'], scope: 'test')]
#[Group('Financer')]
#[TestGroup('financer')]
class StatusFilterTest extends TestCase
{
    use DatabaseTransactions;

    private StatusFilter $filter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->filter = new StatusFilter;
    }

    #[Test]
    public function it_filters_by_single_status(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $archivedFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
        ]);

        $query = Financer::query();
        request()->merge(['status' => 'active']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(1, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertFalse($financers->contains($pendingFinancer));
        $this->assertFalse($financers->contains($archivedFinancer));
    }

    #[Test]
    public function it_filters_by_multiple_statuses(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $archivedFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
        ]);

        $query = Financer::query();
        request()->merge(['status' => 'active,pending']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(2, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
        $this->assertFalse($financers->contains($archivedFinancer));
    }

    #[Test]
    public function it_filters_by_all_statuses(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $archivedFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ARCHIVED,
        ]);

        $query = Financer::query();
        request()->merge(['status' => 'active,pending,archived']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(3, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
        $this->assertTrue($financers->contains($archivedFinancer));
    }

    #[Test]
    public function it_ignores_invalid_statuses(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $query = Financer::query();
        request()->merge(['status' => 'active,invalid_status,pending']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(2, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
    }

    #[Test]
    public function it_returns_all_records_when_no_status_is_provided(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $query = Financer::query();
        // No status parameter

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(2, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
    }

    #[Test]
    public function it_returns_all_records_when_empty_status_is_provided(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $query = Financer::query();
        request()->merge(['status' => '']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(2, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
    }

    #[Test]
    public function it_returns_all_records_when_only_invalid_statuses_are_provided(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $query = Financer::query();
        request()->merge(['status' => 'invalid,also_invalid']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(1, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
    }

    #[Test]
    public function it_handles_statuses_with_whitespace(): void
    {
        // Arrange
        $division = Division::factory()->create();

        $activeFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::ACTIVE,
        ]);

        $pendingFinancer = Financer::factory()->create([
            'division_id' => $division->id,
            'status' => FinancerStatus::PENDING,
        ]);

        $query = Financer::query();
        request()->merge(['status' => ' active , pending ']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $financers = $result->get();
        $this->assertCount(2, $financers);
        $this->assertTrue($financers->contains($activeFinancer));
        $this->assertTrue($financers->contains($pendingFinancer));
    }
}
