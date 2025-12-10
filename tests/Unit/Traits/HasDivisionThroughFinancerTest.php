<?php

declare(strict_types=1);

namespace Tests\Unit\Traits;

use App\Integrations\Survey\Models\Theme;
use App\Models\Segment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('traits')]
#[Group('division')]
class HasDivisionThroughFinancerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function models_using_has_financer_expose_division_relation(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $segment = Segment::factory()
            ->for($financer, 'financer')
            ->create();

        $segment->load('division');

        $this->assertTrue($segment->relationLoaded('division'));
        $this->assertTrue($segment->division->is($division));
    }

    #[Test]
    public function models_with_nullable_financer_return_null_division_without_financer(): void
    {
        $theme = Theme::factory()->create(['financer_id' => null]);

        $this->assertNull($theme->division);
    }

    #[Test]
    public function models_with_nullable_financer_return_division_when_financer_exists(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $theme = Theme::factory()
            ->for($financer, 'financer')
            ->create();

        $this->assertTrue($theme->division->is($division));
    }
}
