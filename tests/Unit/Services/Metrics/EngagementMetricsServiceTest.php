<?php

namespace Tests\Unit\Services\Metrics;

use App\Models\EngagementLog;
use App\Services\EngagementMetricsService;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
#[Group('engagement')]
class EngagementMetricsServiceTest extends TestCase
{
    protected EngagementMetricsService $service;

    protected Carbon $from;

    protected Carbon $to;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the engagement_logs table is empty
        EngagementLog::query()->delete();

        $this->service = new EngagementMetricsService;

        // Default period: last 7 days
        $this->from = now()->subDays(7)->startOfDay();
        $this->to = now()->endOfDay();
    }

    #[Test]
    public function it_calculates_module_usage_stats(): void
    {
        EngagementLog::factory()->count(3)->create([
            'type' => 'ModuleUsed',
            'target' => 'wellbeing',
            'logged_at' => now()->subDays(1),
        ]);

        EngagementLog::factory()->count(2)->create([
            'type' => 'ModuleUsed',
            'target' => 'communication-rh',
            'logged_at' => now()->subDays(2),
        ]);

        $stats = $this->service->moduleUsageStats($this->from, $this->to);

        $this->assertEquals(3, $stats['wellbeing']);
        $this->assertEquals(2, $stats['communication-rh']);
    }

    #[Test]
    public function it_calculates_tool_clicks_by_target(): void
    {
        EngagementLog::factory()->count(5)->create([
            'type' => 'ToolClicked',
            'target' => 'tool:slack',
            'logged_at' => now()->subDay(),
        ]);

        $clicks = $this->service->toolClicks($this->from, $this->to);

        $this->assertEquals(5, $clicks['tool:slack']);
    }

    #[Test]
    public function it_calculates_article_views(): void
    {
        EngagementLog::factory()->count(4)->create([
            'type' => 'ArticleViewed',
            'target' => 'article:123',
            'logged_at' => now()->subDay(),
        ]);

        $views = $this->service->articleViews($this->from, $this->to);

        $this->assertEquals(4, $views['article:123']);
    }

    #[Test]
    public function it_calculates_bounce_rate_for_article(): void
    {
        // 2 views
        EngagementLog::factory()->create([
            'type' => 'ArticleViewed',
            'target' => 'article:abc',
            'logged_at' => now()->subDays(3),
        ]);

        EngagementLog::factory()->create([
            'type' => 'ArticleViewed',
            'target' => 'article:abc',
            'logged_at' => now()->subDays(2),
        ]);

        // 1 closure without interaction
        EngagementLog::factory()->create([
            'type' => 'ArticleClosedWithoutInteraction',
            'target' => 'article:abc',
            'logged_at' => now()->subDays(2),
        ]);

        $bounce = $this->service->bounceRateForPage('article:abc', $this->from, $this->to);

        $this->assertEquals(50.0, $bounce);
    }

    #[Test]
    public function it_returns_empty_results_when_no_logs(): void
    {
        $this->assertEquals([], $this->service->moduleUsageStats($this->from, $this->to));
        $this->assertEquals([], $this->service->toolClicks($this->from, $this->to));
        $this->assertEquals([], $this->service->articleViews($this->from, $this->to));
        $this->assertEquals(0.0, $this->service->bounceRateForPage('article:123', $this->from, $this->to));
    }

    #[Test]
    public function it_can_aggregate_all_metrics_for_a_range(): void
    {
        EngagementLog::factory()->create([
            'type' => 'ModuleUsed',
            'target' => 'communication-rh',
            'logged_at' => now()->subDays(1),
        ]);

        EngagementLog::factory()->create([
            'type' => 'ToolClicked',
            'target' => 'tool:teams',
            'logged_at' => now()->subDays(1),
        ]);

        $result = $this->service->calculateRangeMetrics($this->from, $this->to);

        $this->assertArrayHasKey('modules', $result);
        $this->assertArrayHasKey('tools', $result);
        $this->assertEquals(1, $result['modules']['communication-rh']);
        $this->assertEquals(1, $result['tools']['tool:teams']);
    }
}
