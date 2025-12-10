<?php

namespace Tests\Unit\Pipelines;

use App\Models\Financer;
use App\Pipelines\FilterPipelines\FinancerPipeline;
use App\QueryFilters\Contracts\Filter;
use App\QueryFilters\ModelSpecific\Financer\DivisionIdFilter;
use App\QueryFilters\ModelSpecific\Financer\GlobalSearchFilter;
use App\QueryFilters\ModelSpecific\Financer\IbanFilter;
use App\QueryFilters\ModelSpecific\Financer\IdFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationCountryFilter;
use App\QueryFilters\ModelSpecific\Financer\RegistrationNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\RepresentativeIdFilter;
use App\QueryFilters\ModelSpecific\Financer\StatusFilter;
use App\QueryFilters\ModelSpecific\Financer\VatNumberFilter;
use App\QueryFilters\ModelSpecific\Financer\WebsiteFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\NameFilter;
use App\QueryFilters\Shared\RemarksFilter;
use App\QueryFilters\Shared\TimezoneFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('financer')]

class FinancerPipelineTest extends TestCase
{
    private FinancerPipeline $pipeline;

    private Builder $query;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pipeline = new FinancerPipeline;
        $this->query = Financer::query();
    }

    #[Test]
    public function it_applies_filters_to_query(): void
    {
        // Arrange
        $pipelineMock = $this->getMockBuilder(Pipeline::class)
            ->disableOriginalConstructor()
            ->getMock();

        $pipelineMock->expects($this->once())
            ->method('send')
            ->with($this->query)
            ->willReturnSelf();

        $pipelineMock->expects($this->once())
            ->method('through')
            ->with($this->isType('array'))
            ->willReturnSelf();

        $pipelineMock->expects($this->once())
            ->method('thenReturn')
            ->willReturn($this->query);

        App::instance(Pipeline::class, $pipelineMock);

        // Act
        $result = $this->pipeline->apply($this->query);

        // Assert
        $this->assertInstanceOf(Builder::class, $result);
    }

    #[Test]
    public function it_contains_all_required_filters(): void
    {
        // Arrange
        $requiredFilters = [
            // Global search filter
            GlobalSearchFilter::class,

            // Shared generic filters
            NameFilter::class,
            RemarksFilter::class,
            DateFromFilter::class,
            DateToFilter::class,
            TimezoneFilter::class,

            // Financer-specific filters
            IdFilter::class,
            StatusFilter::class,
            RegistrationNumberFilter::class,
            RegistrationCountryFilter::class,
            WebsiteFilter::class,
            IbanFilter::class,
            VatNumberFilter::class,
            RepresentativeIdFilter::class,
            DivisionIdFilter::class,
        ];

        // Use reflection to access protected property
        $reflection = new ReflectionClass($this->pipeline);
        $filtersProperty = $reflection->getProperty('filters');
        $filtersProperty->setAccessible(true);
        $actualFilters = $filtersProperty->getValue($this->pipeline);

        // Assert
        foreach ($requiredFilters as $filter) {
            $this->assertContains($filter, $actualFilters, "Filter {$filter} is missing from the pipeline");
        }
        $this->assertCount(count($requiredFilters), $actualFilters, 'There are more or fewer filters than expected');
    }

    #[Test]
    public function each_filter_is_properly_registered(): void
    {
        // Use reflection to access protected property
        $reflection = new ReflectionClass($this->pipeline);
        $filtersProperty = $reflection->getProperty('filters');
        $filtersProperty->setAccessible(true);
        $filters = $filtersProperty->getValue($this->pipeline);

        // Assert each filter exists and implements the correct interface
        foreach ($filters as $filterClass) {
            $this->assertTrue(class_exists($filterClass), "Filter class {$filterClass} does not exist");

            $reflectionFilter = new ReflectionClass($filterClass);
            $this->assertTrue(
                $reflectionFilter->implementsInterface(Filter::class),
                "Filter {$filterClass} does not implement the Filter interface"
            );
        }
    }
}
