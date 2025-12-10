<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\FinancerMetricsController;

use App\Enums\FinancerMetricType;
use App\Models\Financer;
use App\Models\FinancerMetric;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('api')]
#[Group('metrics')]
#[Group('financer')]
class FinancerMetricsAllEndpointTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        Context::flush();
        config(['metrics.disabled_metrics' => []]);
        $this->financer = Financer::factory()->create();
    }

    private function createAuthUserWithFinancer(?Financer $financer = null): User
    {
        $financer ??= $this->financer;
        $user = $this->createAuthUser();

        // Attach user to financer with active status
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'TEST-'.$user->id,
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);

        return $user;
    }

    #[Test]
    public function it_returns_all_metrics_endpoint(): void
    {
        $user = $this->createAuthUserWithFinancer();
        $user->givePermissionTo('view_financer_metrics');
        $this->createTestMetrics();

        // New security model: provide accessible_financers context and financer_id query param
        Context::add('accessible_financers', [$this->financer->id]);
        // Ensure activeFinancerID() resolves correctly during the request
        Context::add('financer_id', $this->financer->id);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers/metrics/all?period=7d&financer_id='.$this->financer->id);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'active-beneficiaries' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'activation-rate' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'session-time' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'article_viewed' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'module-usage' => [
                    'title',
                    'value',
                    'labels',
                    'datasets',
                ],
                'voucher-purchases' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'shortcuts-clicks' => [
                    'title',
                    'value',
                    'labels',
                    'datasets',
                ],
                'article-reactions' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'articles-per-employee' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'bounce-rate' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
                'voucher-average-amount' => [
                    'title',
                    'value',
                    'labels',
                    'data',
                ],
            ]);
    }

    #[Test]
    public function it_excludes_disabled_metrics_from_all_endpoint(): void
    {
        config(['metrics.disabled_metrics' => [FinancerMetricType::MODULE_USAGE]]);

        $user = $this->createAuthUserWithFinancer();
        $user->givePermissionTo('view_financer_metrics');
        $this->createTestMetrics();

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);

        $response = $this->actingAs($user)
            ->getJson('/api/v1/financers/metrics/all?period=7d&financer_id='.$this->financer->id);

        $response->assertStatus(200);
        $this->assertArrayNotHasKey(
            FinancerMetricType::MODULE_USAGE,
            $response->json()
        );
    }

    #[Test]
    public function it_returns_not_found_when_requesting_disabled_metric(): void
    {
        config(['metrics.disabled_metrics' => [FinancerMetricType::ARTICLE_VIEWED]]);

        $user = $this->createAuthUserWithFinancer();
        $user->givePermissionTo('view_financer_metrics');

        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('financer_id', $this->financer->id);

        $response = $this->actingAs($user)
            ->getJson(sprintf(
                '/api/v1/financers/metrics/%s?period=7d&financer_id=%s',
                FinancerMetricType::ARTICLE_VIEWED,
                $this->financer->id
            ));

        $response->assertStatus(404)
            ->assertJsonFragment(['error' => 'Invalid metric type']);
    }

    private function createTestMetrics(): void
    {
        $baseDate = Carbon::now();

        // Create sample metrics for different types
        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_active_beneficiaries',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'total' => 100,
                'daily' => [
                    ['date' => $baseDate->copy()->subDays(6)->toDateString(), 'count' => 95],
                    ['date' => $baseDate->copy()->subDays(5)->toDateString(), 'count' => 98],
                    ['date' => $baseDate->copy()->subDays(4)->toDateString(), 'count' => 102],
                    ['date' => $baseDate->copy()->subDays(3)->toDateString(), 'count' => 105],
                    ['date' => $baseDate->copy()->subDays(2)->toDateString(), 'count' => 108],
                    ['date' => $baseDate->copy()->subDays(1)->toDateString(), 'count' => 110],
                    ['date' => $baseDate->toDateString(), 'count' => 100],
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_activation_rate',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'rate' => 75.5,
                'total_users' => 200,
                'activated_users' => 151,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_median_session_time',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'median_minutes' => 15,
                'total_sessions' => 450,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_module_usage',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'vouchers' => [
                    'unique_users' => 85,
                    'total_uses' => 320,
                ],
                'hr_tools' => [
                    'unique_users' => 45,
                    'total_uses' => 180,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_article_viewed',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'articles' => [
                    'views' => 250,
                    'unique_users' => 125,
                ],
                'tools' => [
                    'clicks' => 180,
                    'unique_users' => 90,
                ],
                'total_interactions' => 430,
            ],
        ]);

        // Create additional metrics for complete coverage
        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_voucher_purchases',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'total_volume' => 5000,
                'total_purchases' => 50,
                'unique_users' => 30,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_shortcuts_clicks',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'vouchers' => [
                    'total_clicks' => 150,
                    'unique_users' => 75,
                ],
                'hr_tools' => [
                    'total_clicks' => 80,
                    'unique_users' => 40,
                ],
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_article_reactions',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'total_reactions' => 200,
                'unique_users' => 50,
                'unique_articles' => 25,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_articles_per_employee',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'articles_per_employee' => 2.5,
                'total_articles_viewed' => 250,
                'active_employees' => 100,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_bounce_rate',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'bounce_rate' => 15.5,
                'total_sessions' => 500,
                'bounce_sessions' => 78,
            ],
        ]);

        FinancerMetric::create([
            'date_from' => $baseDate->copy()->subDays(6)->toDateString(),
            'date_to' => $baseDate->toDateString(),
            'metric' => 'financer_voucher_average',
            'financer_id' => $this->financer->id,
            'period' => '7d',
            'data' => [
                'average_amount' => 100,
                'total_purchases' => 50,
                'total_volume' => 5000,
            ],
        ]);
    }
}
