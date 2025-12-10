<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\ArticlesPerEmployeeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class ArticlesPerEmployeeCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ArticlesPerEmployeeCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ArticlesPerEmployeeCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::ARTICLES_PER_EMPLOYEE,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_average_articles_per_employee_by_day(): void
    {
        // Create 3 users
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

        // Day 1:
        // User 0 reads 3 articles
        // User 1 reads 2 articles
        // Average = 5/2 = 2.5
        for ($i = 0; $i < 3; $i++) {
            EngagementLog::create([
                'user_id' => $users[0]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.$i,
                'logged_at' => $now->copy()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->startOfDay()->addHours($i),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            EngagementLog::create([
                'user_id' => $users[1]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.($i + 3),
                'logged_at' => $now->copy()->startOfDay()->addHours($i + 3),
                'created_at' => $now->copy()->startOfDay()->addHours($i + 3),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        // Day 2:
        // User 2 reads 4 articles
        // Average = 4/1 = 4.0
        for ($i = 0; $i < 4; $i++) {
            EngagementLog::create([
                'user_id' => $users[2]->id,
                'type' => 'ArticleViewed',
                'target' => 'article_'.($i + 5),
                'logged_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'metadata' => ['financer_id' => $this->financer->id],
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

        // Check daily data
        $this->assertCount(2, $result['daily']);

        // Day 1: Average 2.5 articles per employee
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(2.5, $result['daily'][0]['value']);
        $this->assertEquals(2, $result['daily'][0]['active_employees']);
        $this->assertEquals(5, $result['daily'][0]['total_articles']);

        // Day 2: Average 4.0 articles per employee
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(4.0, $result['daily'][1]['value']);
        $this->assertEquals(1, $result['daily'][1]['active_employees']);
        $this->assertEquals(4, $result['daily'][1]['total_articles']);

        // Overall average: average of daily averages = (2.5 + 4.0) / 2 = 3.25
        $this->assertEquals(3.25, $result['total']);
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
        $this->assertEmpty($result['daily']);
        $this->assertEquals(0, $result['total']);
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
        $this->assertEquals(1.0, $result['daily'][0]['value']);
        $this->assertEquals(1, $result['daily'][0]['active_employees']);
        $this->assertEquals(1, $result['daily'][0]['total_articles']);
    }

    #[Test]
    public function it_counts_unique_articles_per_user(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now()->startOfDay()->addHours(8); // Start at 8 AM to avoid edge cases

        // User reads the same article 3 times
        for ($i = 0; $i < 3; $i++) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'ArticleViewed',
                'target' => 'test_unique_article_1', // Same article with unique prefix
                'logged_at' => $now->copy()->addMinutes($i * 10),
                'created_at' => $now->copy()->addMinutes($i * 10),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        // User reads 2 different articles
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'test_unique_article_2',
            'logged_at' => $now->copy()->addHour(),
            'created_at' => $now->copy()->addHour(),
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ArticleViewed',
            'target' => 'test_unique_article_3',
            'logged_at' => $now->copy()->addHours(2),
            'created_at' => $now->copy()->addHours(2),
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // User read 3 unique articles (test_unique_article_1 counted only once despite 3 views)
        $this->assertEquals(3.0, $result['daily'][0]['value']);
        $this->assertEquals(1, $result['daily'][0]['active_employees']);
        $this->assertEquals(3, $result['daily'][0]['total_articles']);
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

        // First day has 1 article per employee
        $this->assertEquals(1.0, $result['daily'][0]['value']);

        // Other days should be 0
        $this->assertEquals(0, $result['daily'][1]['value']);
        $this->assertEquals(0, $result['daily'][2]['value']);
    }

    #[Test]
    public function it_calculates_overall_average_correctly(): void
    {
        // Create 2 users
        $users = User::factory()->count(2)->create();
        foreach ($users as $user) {
            FinancerUser::create([
                'financer_id' => $this->financer->id,
                'user_id' => $user->id,
                'active' => true,
                'role' => RoleDefaults::BENEFICIARY,
            ]);
        }

        $now = Carbon::now();

        // Day 1: User 0 reads 2 articles
        for ($i = 0; $i < 2; $i++) {
            EngagementLog::create([
                'user_id' => $users[0]->id,
                'type' => 'ArticleViewed',
                'target' => 'day1_article_'.$i,
                'logged_at' => $now->copy()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->startOfDay()->addHours($i),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        // Day 2: User 1 reads 4 articles
        for ($i = 0; $i < 4; $i++) {
            EngagementLog::create([
                'user_id' => $users[1]->id,
                'type' => 'ArticleViewed',
                'target' => 'day2_article_'.$i,
                'logged_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'created_at' => $now->copy()->addDay()->startOfDay()->addHours($i),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Check that we have data for both days
        $this->assertCount(2, $result['daily']);

        // Day 1: 2 articles / 1 employee = 2.0
        $this->assertEquals(2.0, $result['daily'][0]['value']);

        // Day 2: 4 articles / 1 employee = 4.0
        $this->assertEquals(4.0, $result['daily'][1]['value']);

        // Overall average: average of daily averages = (2.0 + 4.0) / 2 = 3.0
        $this->assertEquals(3.0, $result['total']);
    }
}
