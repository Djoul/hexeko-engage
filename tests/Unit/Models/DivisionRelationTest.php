<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Integrations\HRTools\Models\Link;
use App\Models\Audit;
use App\Models\CreditBalance;
use App\Models\DivisionIntegration;
use App\Models\DivisionModule;
use App\Models\Financer;
use App\Models\FinancerBalance;
use App\Models\FinancerIntegration;
use App\Models\FinancerMetric;
use App\Models\FinancerModule;
use App\Models\FinancerUser;
use App\Models\Integration;
use App\Models\LLMRequest;
use App\Models\MobileVersionLog;
use App\Models\Module;
use App\Models\NotificationTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('division')]
class DivisionRelationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function notification_topics_can_access_their_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $topic = NotificationTopic::factory()->create([
            'financer_id' => $financer->id,
        ]);

        $this->assertTrue($topic->division->is($division));
    }

    #[Test]
    public function financer_user_pivot_exposes_division_through_financer(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'financers' => [
                [
                    'financer' => $financer,
                ],
            ],
        ]);

        $pivot = FinancerUser::query()
            ->where('financer_id', $financer->id)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $this->assertTrue($pivot->division->is($division));
    }

    #[Test]
    public function financer_module_pivot_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $module = Module::factory()->create();

        $financer->modules()->attach($module->id, [
            'active' => true,
            'promoted' => false,
            'price_per_beneficiary' => 100,
        ]);

        $pivot = FinancerModule::query()
            ->where('financer_id', $financer->id)
            ->where('module_id', $module->id)
            ->firstOrFail();

        $this->assertTrue($pivot->division->is($division));
    }

    #[Test]
    public function financer_integration_pivot_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $integration = Integration::factory()->create();

        $financer->integrations()->attach($integration->id, [
            'active' => true,
        ]);

        $pivot = FinancerIntegration::query()
            ->where('financer_id', $financer->id)
            ->where('integration_id', $integration->id)
            ->firstOrFail();

        $this->assertTrue($pivot->division->is($division));
    }

    #[Test]
    public function division_module_pivot_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $module = Module::factory()->create();

        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 50,
        ]);

        $pivot = DivisionModule::query()
            ->where('division_id', $division->id)
            ->where('module_id', $module->id)
            ->firstOrFail();

        $this->assertTrue($pivot->division->is($division));
    }

    #[Test]
    public function division_integration_pivot_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $integration = Integration::factory()->create();

        $division->integrations()->attach($integration->id, [
            'active' => true,
        ]);

        $pivot = DivisionIntegration::query()
            ->where('division_id', $division->id)
            ->where('integration_id', $integration->id)
            ->firstOrFail();

        $this->assertTrue($pivot->division->is($division));
    }

    #[Test]
    public function mobile_version_logs_expose_division_through_financer(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'financers' => [
                [
                    'financer' => $financer,
                ],
            ],
        ]);

        $log = MobileVersionLog::factory()->create([
            'financer_id' => $financer->id,
            'user_id' => $user->id,
        ]);

        $this->assertTrue($log->division->is($division));
    }

    #[Test]
    public function llm_requests_expose_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $request = LLMRequest::create([
            'prompt' => 'prompt',
            'response' => 'response',
            'tokens_used' => 10,
            'engine_used' => 'gpt',
            'financer_id' => $financer->id,
            'requestable_id' => $financer->id,
            'requestable_type' => Financer::class,
            'prompt_system' => null,
        ]);

        $this->assertTrue($request->division->is($division));
    }

    #[Test]
    public function hrtools_links_expose_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $link = Link::factory()->create([
            'financer_id' => $financer->id,
        ]);

        $this->assertTrue($link->division->is($division));
    }

    #[Test]
    public function users_expose_divisions_relation_via_financers(): void
    {
        $divisionA = ModelFactory::createDivision();
        $divisionB = ModelFactory::createDivision();
        $financerA = ModelFactory::createFinancer(['division_id' => $divisionA->id]);
        $financerB = ModelFactory::createFinancer(['division_id' => $divisionB->id]);

        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $financerA],
                ['financer' => $financerB],
            ],
        ]);

        $divisionIds = $user->divisions()->pluck('divisions.id')->all();

        $this->assertEqualsCanonicalizing([$divisionA->id, $divisionB->id], $divisionIds);
    }

    #[Test]
    public function audits_expose_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $audit = Audit::create([
            'user_type' => Financer::class,
            'user_id' => $financer->id,
            'financer_id' => $financer->id,
            'event' => 'created',
            'auditable_id' => $financer->id,
            'auditable_type' => Financer::class,
            'old_values' => [],
            'new_values' => [],
            'url' => 'http://example.com',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
        ]);

        $this->assertTrue($audit->division->is($division));
    }

    #[Test]
    public function financer_metrics_expose_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $metric = FinancerMetric::create([
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->toDateString(),
            'metric' => 'financer_test_metric',
            'financer_id' => $financer->id,
            'period' => '1d',
            'data' => ['value' => 1],
        ]);

        $this->assertTrue($metric->division->is($division));
    }

    #[Test]
    public function credit_balance_owned_by_financer_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $balance = CreditBalance::create([
            'owner_type' => Financer::class,
            'owner_id' => $financer->id,
            'type' => 'tokens',
            'balance' => 100,
            'context' => [],
        ]);

        $this->assertTrue($balance->division->is($division));
    }

    #[Test]
    public function credit_balance_owned_by_user_uses_user_financer_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'financers' => [
                [
                    'financer' => $financer,
                ],
            ],
        ]);

        $balance = CreditBalance::create([
            'owner_type' => User::class,
            'owner_id' => $user->id,
            'type' => 'tokens',
            'balance' => 50,
            'context' => [],
        ]);

        $this->assertTrue($balance->division->is($division));
    }

    #[Test]
    public function financer_balance_exposes_division(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        $balance = FinancerBalance::create([
            'financer_id' => $financer->id,
            'balance' => 0,
        ]);

        $this->assertTrue($balance->division->is($division));
    }
}
