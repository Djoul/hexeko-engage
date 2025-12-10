<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\HrCommunicationsCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('metrics')]
class HrCommunicationsCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private HrCommunicationsCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new HrCommunicationsCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::ARTICLE_VIEWED,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_unique_article_views_by_user(): void
    {
        // Create users
        $users = User::factory()->count(3)->create();
        foreach ($users as $user) {
            FinancerUser::create([
                'financer_id' => $this->financer->id,
                'user_id' => $user->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $now = Carbon::now();
        $sessionId = Uuid::uuid4()->toString();

        // Day 1: User 0 views 3 different articles
        for ($i = 0; $i < 3; $i++) {
            EngagementLog::create([
                'user_id' => $users[0]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.$i,
                'logged_at' => $now->copy()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->startOfDay()->addHours($i),
                'metadata' => [
                    'session_id' => $sessionId,
                    'financer_id' => $this->financer->id,
                ],
            ]);
        }

        // User 1 views 2 different articles
        for ($i = 0; $i < 2; $i++) {
            EngagementLog::create([
                'user_id' => $users[1]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.($i + 3),
                'logged_at' => $now->copy()->startOfDay()->addHours($i + 3),
                'created_at' => $now->copy()->startOfDay()->addHours($i + 3),
                'metadata' => [
                    'session_id' => Uuid::uuid4()->toString(),
                    'financer_id' => $this->financer->id,
                ],
            ]);
        }

        // Day 2: User 2 views 3 different articles
        for ($i = 0; $i < 3; $i++) {
            EngagementLog::create([
                'user_id' => $users[2]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.($i + 5),
                'logged_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'metadata' => [
                    'session_id' => Uuid::uuid4()->toString(),
                    'financer_id' => $this->financer->id,
                ],
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Check structure
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('unique_users', $result);

        // Check daily data
        $this->assertCount(2, $result['daily']);

        // Day 1: 5 unique article views, 2 unique users
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(5, $result['daily'][0]['count']);
        $this->assertEquals(2, $result['daily'][0]['unique_users']);

        // Day 2: 3 unique article views, 1 unique user
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(3, $result['daily'][1]['count']);
        $this->assertEquals(1, $result['daily'][1]['unique_users']);

        // Check totals
        $this->assertEquals(8, $result['total']);
        $this->assertEquals(3, $result['unique_users']);
    }

    #[Test]
    public function it_returns_empty_data_when_no_financer_users(): void
    {
        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertArrayHasKey('unique_users', $result);
        $this->assertEmpty($result['daily']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['unique_users']);
    }

    #[Test]
    public function it_only_counts_active_financer_users(): void
    {
        $activeUser = User::factory()->create();
        $inactiveUser = User::factory()->create();

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $activeUser->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $inactiveUser->id,
            'active' => false,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create article views for both users
        EngagementLog::create([
            'user_id' => $activeUser->id,
            'type' => 'ArticleViewed',
            'target' => 'article_1',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $inactiveUser->id,
            'type' => 'ArticleViewed',
            'target' => 'article_2',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count active user's view
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['count']);
        $this->assertEquals(1, $result['unique_users']);
    }

    #[Test]
    public function it_counts_unique_users_correctly_across_days(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Same user views different articles on multiple days
        for ($day = 0; $day < 3; $day++) {
            for ($i = 0; $i < 2; $i++) {
                EngagementLog::create([
                    'user_id' => $user->id,
                    'type' => 'ArticleViewed',
                    'target' => 'article_'.($day * 2 + $i), // Different articles each time
                    'logged_at' => $now->copy()->addDays($day)->startOfDay()->addHours($i),
                    'created_at' => $now->copy()->addDays($day)->startOfDay()->addHours($i),
                    'metadata' => [
                        'session_id' => Uuid::uuid4()->toString(),
                        'financer_id' => $this->financer->id,
                    ],
                ]);
            }
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDays(2)->endOfDay(),
            'daily'
        );

        // Total unique users should be 1 (same user across all days)
        $this->assertEquals(1, $result['unique_users']);

        // Total unique article views should be 6 (each article viewed once by the user)
        $this->assertEquals(6, $result['total']);

        // Each day should show 1 unique user and 2 unique article views
        foreach ($result['daily'] as $dayData) {
            $this->assertEquals(1, $dayData['unique_users']);
            $this->assertEquals(2, $dayData['count']);
        }
    }

    #[Test]
    public function it_counts_same_article_only_once_per_user(): void
    {
        // Create a user
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // User views the same article 5 times during the day
        for ($i = 0; $i < 5; $i++) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'ArticleViewed',
                'target' => 'article_1', // Same article
                'logged_at' => $now->copy()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->startOfDay()->addHours($i),
                'metadata' => [
                    'session_id' => Uuid::uuid4()->toString(),
                    'financer_id' => $this->financer->id,
                ],
            ]);
        }

        // User views 2 other articles once each
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article_2',
            'logged_at' => $now->copy()->startOfDay()->addHours(6),
            'created_at' => $now->copy()->startOfDay()->addHours(6),
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article_3',
            'logged_at' => $now->copy()->startOfDay()->addHours(7),
            'created_at' => $now->copy()->startOfDay()->addHours(7),
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // User viewed 3 unique articles (article_1, article_2, article_3)
        // Even though article_1 was viewed 5 times, it should count as 1
        $this->assertEquals(3, $result['total']);
        $this->assertEquals(3, $result['daily'][0]['count']);
        $this->assertEquals(1, $result['unique_users']);
    }

    #[Test]
    public function it_returns_zero_for_days_without_views(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create views only on first day
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'article_1',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        // Calculate for 3 days
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDays(2)->endOfDay(),
            'daily'
        );

        $this->assertCount(3, $result['daily']);

        // First day has views
        $this->assertEquals(1, $result['daily'][0]['count']);

        // Other days should be 0
        $this->assertEquals(0, $result['daily'][1]['count']);
        $this->assertEquals(0, $result['daily'][2]['count']);
    }
}
