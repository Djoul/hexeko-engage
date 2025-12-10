<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\Models\FinancerModule;
use App\Models\Module;
use App\Services\Invoicing\GetModuleActivationHistoryService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
class GetModuleActivationHistoryServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GetModuleActivationHistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GetModuleActivationHistoryService::class);
    }

    #[Test]
    public function it_returns_ordered_activation_history(): void
    {
        $financer = ModelFactory::createFinancer();
        $module = Module::factory()->create();

        $financer->modules()->attach($module->id, [
            'active' => false,
            'created_at' => Carbon::parse('2025-01-01'),
        ]);

        $financerModuleId = DB::table('financer_module')
            ->where('financer_id', $financer->id)
            ->where('module_id', $module->id)
            ->value('id');

        DB::table('audits')->insert([
            'auditable_type' => FinancerModule::class,
            'auditable_id' => (string) $financerModuleId,
            'event' => 'updated',
            'old_values' => json_encode(['active' => false], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['active' => true], JSON_THROW_ON_ERROR),
            'financer_id' => $financer->id,
            'created_at' => Carbon::parse('2025-02-01 10:00:00'),
            'updated_at' => Carbon::parse('2025-02-01 10:00:00'),
        ]);

        DB::table('audits')->insert([
            'auditable_type' => FinancerModule::class,
            'auditable_id' => (string) $financerModuleId,
            'event' => 'updated',
            'old_values' => json_encode(['active' => true], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['active' => false], JSON_THROW_ON_ERROR),
            'financer_id' => $financer->id,
            'created_at' => Carbon::parse('2025-02-20 09:00:00'),
            'updated_at' => Carbon::parse('2025-02-20 09:00:00'),
        ]);

        $history = $this->service->getActivationHistory(
            $financer->id,
            $module->id,
            Carbon::parse('2025-02-01'),
            Carbon::parse('2025-02-28')
        );

        $this->assertCount(2, $history);
        $this->assertSame('activated', $history[0]['event']);
        $this->assertSame('2025-02-01T10:00:00+00:00', $history[0]['at']);
        $this->assertSame('deactivated', $history[1]['event']);
        $this->assertSame('2025-02-20T09:00:00+00:00', $history[1]['at']);
    }

    #[Test]
    public function it_checks_if_module_is_active_on_given_date(): void
    {
        $financer = ModelFactory::createFinancer();
        $module = Module::factory()->create();

        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => Carbon::parse('2025-01-01'),
        ]);

        $financerModuleId = DB::table('financer_module')
            ->where('financer_id', $financer->id)
            ->where('module_id', $module->id)
            ->value('id');

        DB::table('audits')->insert([
            'auditable_type' => FinancerModule::class,
            'auditable_id' => (string) $financerModuleId,
            'event' => 'updated',
            'old_values' => json_encode(['active' => true], JSON_THROW_ON_ERROR),
            'new_values' => json_encode(['active' => false], JSON_THROW_ON_ERROR),
            'financer_id' => $financer->id,
            'created_at' => Carbon::parse('2025-03-10 12:00:00'),
            'updated_at' => Carbon::parse('2025-03-10 12:00:00'),
        ]);

        $isActiveMidFebruary = $this->service->isModuleActiveInPeriod(
            $financer->id,
            $module->id,
            Carbon::parse('2025-02-15')
        );

        $isActiveAfterDeactivation = $this->service->isModuleActiveInPeriod(
            $financer->id,
            $module->id,
            Carbon::parse('2025-04-01')
        );

        $this->assertTrue($isActiveMidFebruary);
        $this->assertFalse($isActiveAfterDeactivation);
    }
}
