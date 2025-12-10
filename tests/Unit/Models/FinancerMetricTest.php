<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\EngagementMetric;
use App\Models\FinancerMetric;
use App\Models\Traits\FinancerMetricable;
use App\Traits\GlobalCachable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
#[Group('financer')]
class FinancerMetricTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_exists_as_a_model(): void
    {
        $this->assertTrue(
            class_exists(FinancerMetric::class),
            'FinancerMetric model should exist'
        );
    }

    #[Test]
    public function it_extends_engagement_metric(): void
    {
        $model = new FinancerMetric;

        $this->assertInstanceOf(
            EngagementMetric::class,
            $model,
            'FinancerMetric should extend EngagementMetric'
        );
    }

    #[Test]
    public function it_uses_global_cachable_trait(): void
    {
        $this->assertTrue(
            in_array(GlobalCachable::class, class_uses(FinancerMetric::class)),
            'FinancerMetric should use GlobalCachable trait'
        );
    }

    #[Test]
    public function it_uses_financer_metricable_trait(): void
    {
        $this->assertTrue(
            in_array(FinancerMetricable::class, class_uses(FinancerMetric::class)),
            'FinancerMetric should use FinancerMetricable trait'
        );
    }

    #[Test]
    public function it_has_correct_cache_ttl(): void
    {
        $model = new FinancerMetric;

        // Check if cache TTL is set to 1 hour (3600 seconds)
        $this->assertEquals(
            3600,
            $model::getCacheTtl(),
            'Cache TTL should be 1 hour (3600 seconds)'
        );
    }

    #[Test]
    public function it_uses_engagement_metrics_table(): void
    {
        $model = new FinancerMetric;

        $this->assertEquals(
            'engagement_metrics',
            $model->getTable(),
            'Should use engagement_metrics table'
        );
    }

    #[Test]
    public function it_has_static_cache_methods_from_global_cachable(): void
    {
        // Check that GlobalCachable provides these static methods
        $this->assertTrue(
            method_exists(FinancerMetric::class, 'findCached'),
            'Should have findCached method from GlobalCachable'
        );

        $this->assertTrue(
            method_exists(FinancerMetric::class, 'allCached'),
            'Should have allCached method from GlobalCachable'
        );

        $this->assertTrue(
            method_exists(FinancerMetric::class, 'refreshAllCache'),
            'Should have refreshAllCache method from GlobalCachable'
        );
    }

    #[Test]
    public function it_has_financer_scopes_from_trait(): void
    {
        // Check that FinancerMetricable provides these scopes
        $model = new FinancerMetric;

        $this->assertTrue(
            method_exists($model, 'scopeByFinancer'),
            'Should have scopeByFinancer method from FinancerMetricable'
        );

        $this->assertTrue(
            method_exists($model, 'scopeByDateRange'),
            'Should have scopeByDateRange method from FinancerMetricable'
        );

        $this->assertTrue(
            method_exists($model, 'scopeByMetricType'),
            'Should have scopeByMetricType method from FinancerMetricable'
        );

        $this->assertTrue(
            method_exists($model, 'scopeLatest'),
            'Should have scopeLatest method from FinancerMetricable'
        );
    }

    #[Test]
    public function it_has_financer_relationship(): void
    {
        $model = new FinancerMetric;

        $this->assertTrue(
            method_exists($model, 'financer'),
            'Should have financer relationship from FinancerMetricable'
        );
    }

    #[Test]
    public function it_provides_financer_cache_methods(): void
    {
        $model = new FinancerMetric;

        $this->assertTrue(
            method_exists($model, 'getFinancerCacheKey'),
            'Should have getFinancerCacheKey method'
        );

        $this->assertTrue(
            method_exists($model, 'getFinancerCacheTag'),
            'Should have getFinancerCacheTag method'
        );
    }
}
