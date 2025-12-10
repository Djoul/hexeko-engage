<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\HRTools\Models\Link;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Metrics\Calculators\ShortcutsClicksCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class ShortcutsClicksCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ShortcutsClicksCalculator $calculator;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ShortcutsClicksCalculator;
        $this->financer = Financer::factory()->create();
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::SHORTCUTS_CLICKS,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_shortcuts_clicks_by_day_and_type(): void
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

        // Create HRTools Links
        $links = [];
        $linkNames = ['Benefits Portal', 'Payslip Access', 'HR Tools', 'Savings Plan'];
        foreach ($linkNames as $index => $name) {
            $links[$name] = Link::create([
                'financer_id' => $this->financer->id,
                'name' => $name,
                'url' => 'https://example.com/'.strtolower(str_replace(' ', '-', $name)),
                'position' => $index + 1,
            ]);
        }

        $now = Carbon::now();

        // Day 1: Various shortcut clicks
        // User 0 clicks Benefits Portal 2 times and Payslip Access 1 time
        EngagementLog::create([
            'user_id' => $users[0]->id,
            'type' => 'LinkClicked',
            'target' => $links['Benefits Portal']->id,
            'logged_at' => $now->copy()->startOfDay()->addHours(1),
            'created_at' => $now->copy()->startOfDay()->addHours(1),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Benefits Portal']->url,
            ],
        ]);

        EngagementLog::create([
            'user_id' => $users[0]->id,
            'type' => 'LinkClicked',
            'target' => $links['Benefits Portal']->id,
            'logged_at' => $now->copy()->startOfDay()->addHours(2),
            'created_at' => $now->copy()->startOfDay()->addHours(2),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Benefits Portal']->url,
            ],
        ]);

        EngagementLog::create([
            'user_id' => $users[0]->id,
            'type' => 'LinkClicked',
            'target' => $links['Payslip Access']->id,
            'logged_at' => $now->copy()->startOfDay()->addHours(3),
            'created_at' => $now->copy()->startOfDay()->addHours(3),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Payslip Access']->url,
            ],
        ]);

        // User 1 clicks HR Tools 1 time
        EngagementLog::create([
            'user_id' => $users[1]->id,
            'type' => 'LinkClicked',
            'target' => $links['HR Tools']->id,
            'logged_at' => $now->copy()->startOfDay()->addHours(4),
            'created_at' => $now->copy()->startOfDay()->addHours(4),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['HR Tools']->url,
            ],
        ]);

        // Day 2: More clicks
        // User 2 clicks Savings Plan 2 times and Benefits Portal 1 time
        EngagementLog::create([
            'user_id' => $users[2]->id,
            'type' => 'LinkClicked',
            'target' => $links['Savings Plan']->id,
            'logged_at' => $now->copy()->addDay()->startOfDay()->addHours(1),
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(1),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Savings Plan']->url,
            ],
        ]);

        EngagementLog::create([
            'user_id' => $users[2]->id,
            'type' => 'LinkClicked',
            'target' => $links['Savings Plan']->id,
            'logged_at' => $now->copy()->addDay()->startOfDay()->addHours(2),
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(2),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Savings Plan']->url,
            ],
        ]);

        EngagementLog::create([
            'user_id' => $users[2]->id,
            'type' => 'LinkClicked',
            'target' => $links['Benefits Portal']->id,
            'logged_at' => $now->copy()->addDay()->startOfDay()->addHours(3),
            'created_at' => $now->copy()->addDay()->startOfDay()->addHours(3),
            'metadata' => [
                'financer_id' => $this->financer->id,
                'url' => $links['Benefits Portal']->url,
            ],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->addDay()->endOfDay(),
            'daily'
        );

        // Check structure
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('shortcuts', $result);
        $this->assertArrayHasKey('total', $result);

        // Check daily data
        $this->assertCount(2, $result['daily']);

        // Day 1: 4 total clicks
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);
        $this->assertEquals(4, $result['daily'][0]['total']);
        $this->assertEquals(2, $result['daily'][0]['shortcuts']['Benefits Portal']);
        $this->assertEquals(1, $result['daily'][0]['shortcuts']['Payslip Access']);
        $this->assertEquals(1, $result['daily'][0]['shortcuts']['HR Tools']);
        $this->assertEquals(0, $result['daily'][0]['shortcuts']['Savings Plan']);

        // Day 2: 3 total clicks
        $this->assertEquals($now->copy()->addDay()->toDateString(), $result['daily'][1]['date']);
        $this->assertEquals(3, $result['daily'][1]['total']);
        $this->assertEquals(1, $result['daily'][1]['shortcuts']['Benefits Portal']);
        $this->assertEquals(0, $result['daily'][1]['shortcuts']['Payslip Access']);
        $this->assertEquals(0, $result['daily'][1]['shortcuts']['HR Tools']);
        $this->assertEquals(2, $result['daily'][1]['shortcuts']['Savings Plan']);

        // Check shortcuts totals
        $this->assertEquals(3, $result['shortcuts']['Benefits Portal']['total']);
        $this->assertEquals(1, $result['shortcuts']['Payslip Access']['total']);
        $this->assertEquals(1, $result['shortcuts']['HR Tools']['total']);
        $this->assertEquals(2, $result['shortcuts']['Savings Plan']['total']);

        // Check total
        $this->assertEquals(7, $result['total']);
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
        $this->assertArrayHasKey('shortcuts', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEmpty($result['daily']);
        $this->assertEmpty($result['shortcuts']);
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

        // Create a link
        $link = Link::create([
            'financer_id' => $this->financer->id,
            'name' => 'Test Link',
            'url' => 'https://example.com/test',
            'position' => 1,
        ]);

        $now = Carbon::now();

        // Create clicks for both users
        EngagementLog::create([
            'user_id' => $activeUser->id,
            'type' => 'LinkClicked',
            'target' => $link->id,
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $inactiveUser->id,
            'type' => 'LinkClicked',
            'target' => $link->id,
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

        // Should only count active user's click
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['daily'][0]['total']);
        $this->assertEquals(1, $result['shortcuts']['Test Link']['total']);
    }

    #[Test]
    public function it_handles_multiple_shortcuts_correctly(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();
        $linkNames = ['Dashboard', 'Profile', 'Settings', 'Help', 'Logout'];
        $links = [];

        // Create links
        foreach ($linkNames as $index => $name) {
            $links[$name] = Link::create([
                'financer_id' => $this->financer->id,
                'name' => $name,
                'url' => 'https://example.com/'.strtolower($name),
                'position' => $index + 1,
            ]);
        }

        // Create clicks for various shortcuts
        foreach ($links as $name => $link) {
            // Each link gets array_search($name) + 1 clicks
            $clickCount = array_search($name, $linkNames) + 1;
            for ($i = 0; $i < $clickCount; $i++) {
                EngagementLog::create([
                    'user_id' => $user->id,
                    'type' => 'LinkClicked',
                    'target' => $link->id,
                    'logged_at' => $now->copy()->addMinutes(array_search($name, $linkNames) * 10 + $i),
                    'created_at' => $now->copy()->addMinutes(array_search($name, $linkNames) * 10 + $i),
                    'metadata' => [
                        'financer_id' => $this->financer->id,
                        'url' => $link->url,
                    ],
                ]);
            }
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Check all shortcuts are present
        foreach ($linkNames as $index => $name) {
            $this->assertArrayHasKey($name, $result['shortcuts']);
            $this->assertEquals($index + 1, $result['shortcuts'][$name]['total']);
        }

        // Total should be 1+2+3+4+5 = 15
        $this->assertEquals(15, $result['total']);
    }

    #[Test]
    public function it_returns_zero_for_days_without_clicks(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $link = Link::create([
            'financer_id' => $this->financer->id,
            'name' => 'Test Link',
            'url' => 'https://example.com/test',
            'position' => 1,
        ]);

        $now = Carbon::now();

        // Create click only on first day
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'LinkClicked',
            'target' => $link->id,
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

        // First day has click
        $this->assertEquals(1, $result['daily'][0]['total']);

        // Other days should be 0
        $this->assertEquals(0, $result['daily'][1]['total']);
        $this->assertEquals(0, $result['daily'][2]['total']);
    }

    #[Test]
    public function it_ignores_link_clicks_without_target(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $link = Link::create([
            'financer_id' => $this->financer->id,
            'name' => 'Valid Link',
            'url' => 'https://example.com/valid',
            'position' => 1,
        ]);

        $now = Carbon::now();

        // Create valid click
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'LinkClicked',
            'target' => $link->id,
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        // Create invalid click (no target)
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'LinkClicked',
            'target' => null,
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

        // Should only count the valid click
        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['shortcuts']['Valid Link']['total']);
    }
}
