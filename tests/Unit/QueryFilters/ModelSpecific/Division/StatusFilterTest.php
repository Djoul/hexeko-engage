<?php

declare(strict_types=1);

namespace Tests\Unit\QueryFilters\ModelSpecific\Division;

use App\Enums\DivisionStatus;
use App\Models\Division;
use App\QueryFilters\ModelSpecific\Division\StatusFilter;
use Illuminate\Database\Eloquent\Builder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\TestCase;

#[FlushTables(tables: ['divisions'], scope: 'test')]
#[Group('division')]
class StatusFilterTest extends TestCase
{
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
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $archivedDivision = Division::factory()->create([
            'status' => DivisionStatus::ARCHIVED,
        ]);

        $query = Division::query();
        request()->merge(['status' => 'active']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(1, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertFalse($divisions->contains($pendingDivision));
        $this->assertFalse($divisions->contains($archivedDivision));
    }

    #[Test]
    public function it_filters_by_multiple_statuses(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $archivedDivision = Division::factory()->create([
            'status' => DivisionStatus::ARCHIVED,
        ]);

        $query = Division::query();
        request()->merge(['status' => 'active,pending']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
        $this->assertFalse($divisions->contains($archivedDivision));
    }

    #[Test]
    public function it_filters_by_all_statuses(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $archivedDivision = Division::factory()->create([
            'status' => DivisionStatus::ARCHIVED,
        ]);

        $query = Division::query();
        request()->merge(['status' => 'active,pending,archived']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(3, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
        $this->assertTrue($divisions->contains($archivedDivision));
    }

    #[Test]
    public function it_ignores_invalid_statuses(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $query = Division::query();
        request()->merge(['status' => 'active,invalid_status,pending']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
    }

    #[Test]
    public function it_returns_all_records_when_no_status_is_provided(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $query = Division::query();
        // No status parameter

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
    }

    #[Test]
    public function it_returns_all_records_when_empty_status_is_provided(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $query = Division::query();
        request()->merge(['status' => '']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
    }

    #[Test]
    public function it_returns_all_records_when_only_invalid_statuses_are_provided(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $query = Division::query();
        request()->merge(['status' => 'invalid,also_invalid']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(1, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
    }

    #[Test]
    public function it_handles_statuses_with_whitespace(): void
    {
        // Arrange
        $activeDivision = Division::factory()->create([
            'status' => DivisionStatus::ACTIVE,
        ]);

        $pendingDivision = Division::factory()->create([
            'status' => DivisionStatus::PENDING,
        ]);

        $query = Division::query();
        request()->merge(['status' => ' active , pending ']);

        // Act
        $result = $this->filter->handle($query, fn ($q): Builder => $q);

        // Assert
        $divisions = $result->get();
        $this->assertCount(2, $divisions);
        $this->assertTrue($divisions->contains($activeDivision));
        $this->assertTrue($divisions->contains($pendingDivision));
    }
}
