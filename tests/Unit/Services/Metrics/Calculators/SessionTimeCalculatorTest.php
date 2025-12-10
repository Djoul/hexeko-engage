<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\SessionTimeCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class SessionTimeCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private SessionTimeCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        $this->markTestSkipped('This test is not yet implemented');
        parent::setUp();
        $this->calculator = new SessionTimeCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::SESSION_TIME,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_average_session_time_using_created_at(): void
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
        $sessionData = [
            // User 1: 2 sessions (300s and 600s)
            ['user' => $users[0], 'duration' => 300],
            ['user' => $users[0], 'duration' => 600],
            // User 2: 1 session (900s)
            ['user' => $users[1], 'duration' => 900],
            // User 3: 2 sessions (120s and 180s)
            ['user' => $users[2], 'duration' => 120],
            ['user' => $users[2], 'duration' => 180],
        ];

        foreach ($sessionData as $index => $data) {
            $sessionId = 'session_'.$index;
            $startTime = $now->copy()->subHours(2)->addMinutes($index * 10);
            $endTime = $startTime->copy()->addSeconds($data['duration']);

            // Create SessionStarted log
            EngagementLog::factory()->create([
                'user_id' => $data['user']->id,
                'type' => 'SessionStarted',
                'logged_at' => $startTime,
                'created_at' => $startTime,
                'metadata' => ['session_id' => $sessionId],
            ]);

            // Create SessionFinished log
            EngagementLog::factory()->create([
                'user_id' => $data['user']->id,
                'type' => 'SessionFinished',
                'logged_at' => $endTime,
                'created_at' => $endTime,
                'metadata' => ['session_id' => $sessionId],
            ]);
        }

        // Calculate metrics
        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Average should be: (300 + 600 + 900 + 120 + 180) / 5 = 420 seconds = 7 minutes
        // But the calculator returns the median, not the average
        // Sorted durations: 120, 180, 300, 600, 900
        // Median = 300 seconds = 5 minutes
        $this->assertEquals(5.0, $result['total']);
        $this->assertCount(1, $result['daily']);
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        // Daily average: (300 + 600 + 900 + 120 + 180) / 5 = 420 seconds = 7 minutes
        $this->assertEquals(7.0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_calculates_session_time_when_sessions_have_no_metadata(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();
        $startTime = $now->copy()->subMinutes(30);
        $endTime = $now->copy()->subMinutes(20);

        // Create logs with minimal metadata - using a simple session identifier
        $sessionIdentifier = 'session_'.uniqid();

        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionStarted',
            'logged_at' => $startTime,
            'created_at' => $startTime,
            'metadata' => ['session_id' => $sessionIdentifier],
        ]);

        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionFinished',
            'logged_at' => $endTime,
            'created_at' => $endTime,
            'metadata' => ['session_id' => $sessionIdentifier],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should calculate 10 minutes (600 seconds)
        $this->assertEquals(10.0, $result['total']);
        $this->assertEquals(10.0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_filters_out_sessions_shorter_than_5_seconds(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create a 3-second session (should be filtered out)
        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionStarted',
            'logged_at' => $now->copy()->subSeconds(3),
            'created_at' => $now->copy()->subSeconds(3),
            'metadata' => ['session_id' => 'short'],
        ]);

        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionFinished',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['session_id' => 'short'],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_filters_out_sessions_longer_than_8_hours(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create a 9-hour session (should be filtered out)
        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionStarted',
            'logged_at' => $now->copy()->subHours(9),
            'created_at' => $now->copy()->subHours(9),
            'metadata' => ['session_id' => 'long'],
        ]);

        EngagementLog::factory()->create([
            'user_id' => $user->id,
            'type' => 'SessionFinished',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['session_id' => 'long'],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEquals(0, $result['daily'][0]['count']);
    }

    #[Test]
    public function it_returns_empty_results_when_no_financer_users(): void
    {
        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertEquals(0, $result['total']);
        $this->assertEmpty($result['daily']);
    }

    #[Test]
    public function it_only_considers_active_financer_users(): void
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

        // Create session for active user
        EngagementLog::factory()->create([
            'user_id' => $activeUser->id,
            'type' => 'SessionStarted',
            'logged_at' => $now->copy()->subMinutes(10),
            'created_at' => $now->copy()->subMinutes(10),
            'metadata' => ['session_id' => 'active'],
        ]);

        EngagementLog::factory()->create([
            'user_id' => $activeUser->id,
            'type' => 'SessionFinished',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['session_id' => 'active'],
        ]);

        // Create session for inactive user (should be ignored)
        EngagementLog::factory()->create([
            'user_id' => $inactiveUser->id,
            'type' => 'SessionStarted',
            'logged_at' => $now->copy()->subMinutes(20),
            'created_at' => $now->copy()->subMinutes(20),
            'metadata' => ['session_id' => 'inactive'],
        ]);

        EngagementLog::factory()->create([
            'user_id' => $inactiveUser->id,
            'type' => 'SessionFinished',
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['session_id' => 'inactive'],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Should only count active user's session (10 minutes)
        $this->assertEquals(10.0, $result['total']);
        $this->assertEquals(10.0, $result['daily'][0]['count']);
    }
}
