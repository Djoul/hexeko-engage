<?php

namespace Tests\Unit\Services\Metrics\Calculators;

use App\Enums\FinancerMetricType;
use App\Enums\IDP\RoleDefaults;
use App\Enums\ModulesCategories;
use App\Models\EngagementLog;
use App\Models\Financer;
use App\Models\FinancerUser;
use App\Models\Module;
use App\Models\User;
use App\Services\Metrics\Calculators\ModuleUsageCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

#[Group('metrics')]
class ModuleUsageCalculatorTest extends TestCase
{
    use DatabaseTransactions;

    private ModuleUsageCalculator $calculator;

    private Financer $financer;

    /**
     * Module names used by tests; align with calculator which expects
     * targets to reference existing Module ids.
     *
     * @var string[]
     */
    private array $moduleNames = [
        'dashboard', 'vouchers', 'benefits', 'savings', 'lifestyle', 'hr-tools', 'news',
    ];

    /** @var array<string,string> map name => module uuid */
    private array $moduleIdByName = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ModuleUsageCalculator;
        $this->financer = Financer::factory()->create();

        // Ensure all referenced module targets exist in catalog
        foreach ($this->moduleNames as $name) {
            $id = Uuid::uuid7()->toString();
            $this->moduleIdByName[$name] = $id;
            // Create module catalog entry with uuid id and realistic category
            $category = match ($name) {
                'vouchers', 'benefits', 'savings' => ModulesCategories::PURCHASING_POWER,
                'lifestyle' => ModulesCategories::WELLBEING,
                default => ModulesCategories::ENTERPRISE_LIFE,
            };
            Module::factory()->create([
                'id' => $id,
                'name' => ['en-US' => ucfirst($name), 'fr-FR' => ucfirst($name)],
                'active' => true,
                'category' => $category,
            ]);
        }

        // helper sanity guard
        $this->assertNotEmpty($this->moduleIdByName);
    }

    private function mid(string $name): string
    {
        return $this->moduleIdByName[$name];
    }

    #[Test]
    public function it_returns_correct_metric_type(): void
    {
        $this->assertEquals(
            FinancerMetricType::MODULE_USAGE,
            $this->calculator->getMetricType()
        );
    }

    #[Test]
    public function it_calculates_module_usage_with_unique_sessions(): void
    {
        // Create users
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user1->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user2->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();
        $sessionId1 = Uuid::uuid4()->toString();
        $sessionId2 = Uuid::uuid4()->toString();

        // User 1 accesses dashboard and vouchers in session 1
        EngagementLog::create([
            'user_id' => $user1->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('dashboard'),
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['session_id' => $sessionId1, 'financer_id' => $this->financer->id],
        ]);

        // User 1 accesses dashboard again in same session (should count as 1)
        EngagementLog::create([
            'user_id' => $user1->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('dashboard'),
            'logged_at' => $now->copy()->addMinutes(5),
            'created_at' => $now->copy()->addMinutes(5),
            'metadata' => ['session_id' => $sessionId1, 'financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $user1->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('vouchers'),
            'logged_at' => $now->copy()->addMinutes(10),
            'created_at' => $now->copy()->addMinutes(10),
            'metadata' => ['session_id' => $sessionId1, 'financer_id' => $this->financer->id],
        ]);

        // User 2 accesses dashboard in session 2
        EngagementLog::create([
            'user_id' => $user2->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('dashboard'),
            'logged_at' => $now->copy()->addMinutes(15),
            'created_at' => $now->copy()->addMinutes(15),
            'metadata' => ['session_id' => $sessionId2, 'financer_id' => $this->financer->id],
        ]);

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Check structure
        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('modules', $result);
        $this->assertArrayHasKey('total', $result);

        // Check daily data
        $this->assertCount(1, $result['daily']);
        $this->assertEquals($now->toDateString(), $result['daily'][0]['date']);

        // Dashboard: 2 unique sessions (1 per user)
        $this->assertEquals(2, $result['daily'][0]['modules'][$this->mid('dashboard')]);

        // Vouchers: 1 unique session
        $this->assertEquals(1, $result['daily'][0]['modules'][$this->mid('vouchers')]);

        // Check module totals
        $this->assertEquals(2, $result['modules'][$this->mid('dashboard')]);
        $this->assertEquals(1, $result['modules'][$this->mid('vouchers')]);

        // Check total
        $this->assertEquals(3, $result['total']);
    }

    #[Test]
    public function it_counts_events_without_session_id_individually(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create 3 events without session_id
        for ($i = 0; $i < 3; $i++) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'ModuleAccessed',
                'target' => $this->mid('benefits'),
                'logged_at' => $now->copy()->addMinutes($i * 5),
                'created_at' => $now->copy()->addMinutes($i * 5),
                'metadata' => ['financer_id' => $this->financer->id],
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Benefits: 3 total uses (no session deduplication)
        $this->assertEquals(3, $result['daily'][0]['modules'][$this->mid('benefits')]);
        $this->assertEquals(3, $result['modules'][$this->mid('benefits')]);
        $this->assertEquals(3, $result['total']);
    }

    #[Test]
    public function it_returns_empty_array_when_no_financer_users(): void
    {
        $result = $this->calculator->calculate(
            $this->financer->id,
            Carbon::now()->startOfDay(),
            Carbon::now()->endOfDay(),
            'daily'
        );

        $this->assertArrayHasKey('daily', $result);
        $this->assertArrayHasKey('modules', $result);
        $this->assertArrayHasKey('total', $result);
        $this->assertEmpty($result['daily']);
        $this->assertEmpty($result['modules']);
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

        // Both users access modules
        EngagementLog::create([
            'user_id' => $activeUser->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('savings'),
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        EngagementLog::create([
            'user_id' => $inactiveUser->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('savings'),
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

        // Should only count active user
        $this->assertEquals(1, $result['daily'][0]['modules'][$this->mid('savings')]);
        $this->assertEquals(1, $result['modules'][$this->mid('savings')]);
        $this->assertEquals(1, $result['total']);
    }

    #[Test]
    public function it_handles_multiple_modules_per_session(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();
        $sessionId = Uuid::uuid4()->toString();

        $modules = ['dashboard', 'vouchers', 'benefits', 'savings', 'lifestyle'];

        foreach ($modules as $index => $module) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'ModuleAccessed',
                'target' => $this->mid($module),
                'logged_at' => $now->copy()->addMinutes($index * 5),
                'created_at' => $now->copy()->addMinutes($index * 5),
                'metadata' => ['session_id' => $sessionId, 'financer_id' => $this->financer->id],
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // Each module should have 1 total use
        foreach ($modules as $module) {
            $this->assertEquals(1, $result['daily'][0]['modules'][$this->mid($module)]);
            $this->assertEquals(1, $result['modules'][$this->mid($module)]);
        }
        $this->assertEquals(count($modules), $result['total']);
    }

    #[Test]
    public function it_counts_different_sessions_separately(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now()->startOfDay()->addHours(8); // Start at 8 AM to avoid edge cases

        // Create 3 different sessions for the same user, each with unique session ID
        $sessionIds = [];
        for ($i = 0; $i < 3; $i++) {
            $sessionIds[] = Uuid::uuid4()->toString();
        }

        // Create engagement logs with distinct sessions
        foreach ($sessionIds as $index => $sessionId) {
            EngagementLog::create([
                'user_id' => $user->id,
                'type' => 'ModuleAccessed',
                'target' => $this->mid('hr-tools'),
                'logged_at' => $now->copy()->addMinutes($index * 30), // Space them 30 minutes apart
                'created_at' => $now->copy()->addMinutes($index * 30),
                'metadata' => ['session_id' => $sessionId, 'financer_id' => $this->financer->id],
            ]);
        }

        $result = $this->calculator->calculate(
            $this->financer->id,
            $now->copy()->startOfDay(),
            $now->copy()->endOfDay(),
            'daily'
        );

        // hr-tools: 3 total uses (3 different sessions)
        $this->assertEquals(3, $result['daily'][0]['modules'][$this->mid('hr-tools')]);
        $this->assertEquals(3, $result['modules'][$this->mid('hr-tools')]);
        $this->assertEquals(3, $result['total']);
    }

    #[Test]
    public function it_ignores_events_without_target(): void
    {
        $user = User::factory()->create();
        FinancerUser::create([
            'financer_id' => $this->financer->id,
            'user_id' => $user->id,
            'active' => true,
            'role' => RoleDefaults::BENEFICIARY,
        ]);

        $now = Carbon::now();

        // Create event with target
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
            'target' => $this->mid('news'),
            'logged_at' => $now,
            'created_at' => $now,
            'metadata' => ['financer_id' => $this->financer->id],
        ]);

        // Create event without target
        EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
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

        // Should only have news module
        $this->assertCount(1, $result['modules']);
        $this->assertArrayHasKey($this->mid('news'), $result['modules']);
        $this->assertEquals(1, $result['daily'][0]['modules'][$this->mid('news')]);
        $this->assertEquals(1, $result['modules'][$this->mid('news')]);
        $this->assertEquals(1, $result['total']);
    }
}
