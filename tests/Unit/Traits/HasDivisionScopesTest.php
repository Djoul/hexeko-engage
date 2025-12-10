<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Models\Segment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('traits')]
#[Group('division')]
class HasDivisionScopesTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_filters_models_by_single_division(): void
    {
        $divisionA = ModelFactory::createDivision();
        $divisionB = ModelFactory::createDivision();

        $financerA = ModelFactory::createFinancer(['division_id' => $divisionA->id]);
        $financerB = ModelFactory::createFinancer(['division_id' => $divisionB->id]);

        Segment::factory()->create(['financer_id' => $financerA->id]);
        Segment::factory()->create(['financer_id' => $financerB->id]);

        $results = Segment::withoutGlobalScopes()
            ->forDivision($divisionA->id)
            ->get();

        $this->assertCount(1, $results);
        $this->assertEquals($divisionA->id, $results->first()->division->id);
    }

    #[Test]
    public function it_filters_models_by_multiple_divisions(): void
    {
        $divisionA = ModelFactory::createDivision();
        $divisionB = ModelFactory::createDivision();
        $divisionC = ModelFactory::createDivision();

        $financerA = ModelFactory::createFinancer(['division_id' => $divisionA->id]);
        $financerB = ModelFactory::createFinancer(['division_id' => $divisionB->id]);
        $financerC = ModelFactory::createFinancer(['division_id' => $divisionC->id]);

        Segment::factory()->create(['financer_id' => $financerA->id]);
        Segment::factory()->create(['financer_id' => $financerB->id]);
        Segment::factory()->create(['financer_id' => $financerC->id]);

        $results = Segment::withoutGlobalScopes()
            ->forDivision([$divisionA->id, $divisionC->id])
            ->get();

        $this->assertCount(2, $results);
        $this->assertTrue($results->pluck('division.id')->contains($divisionA->id));
        $this->assertTrue($results->pluck('division.id')->contains($divisionC->id));
    }
}
