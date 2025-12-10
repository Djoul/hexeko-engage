<?php

declare(strict_types=1);

namespace Tests\Unit\Enums;

use App\Enums\FinancerMetricType;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('enums')]
#[Group('metrics')]
class FinancerMetricTypeTest extends TestCase
{
    #[Test]
    public function it_returns_all_values_when_no_metrics_are_disabled(): void
    {
        config(['metrics.disabled_metrics' => []]);

        $this->assertSame(FinancerMetricType::getValues(), FinancerMetricType::activeValues());
    }

    #[Test]
    public function it_filters_out_disabled_metrics(): void
    {
        config([
            'metrics.disabled_metrics' => [
                FinancerMetricType::ARTICLE_VIEWED,
                'unknown_metric',
            ],
        ]);

        $activeValues = FinancerMetricType::activeValues();

        $this->assertNotContains(FinancerMetricType::ARTICLE_VIEWED, $activeValues);
        $this->assertContains(FinancerMetricType::ACTIVE_BENEFICIARIES, $activeValues);
        $this->assertFalse(in_array('unknown_metric', $activeValues, true));
    }

    #[Test]
    public function it_exposes_a_route_pattern_without_disabled_metrics(): void
    {
        config(['metrics.disabled_metrics' => [FinancerMetricType::ARTICLE_VIEWED]]);

        $pattern = FinancerMetricType::getRoutePattern();

        $this->assertStringNotContainsString(FinancerMetricType::ARTICLE_VIEWED, $pattern);
        $this->assertStringContainsString(FinancerMetricType::ACTIVE_BENEFICIARIES, $pattern);
    }
}
