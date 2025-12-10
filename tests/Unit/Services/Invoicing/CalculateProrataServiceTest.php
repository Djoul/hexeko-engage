<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\DTOs\Invoicing\ProrataCalculationDTO;
use App\Models\FinancerModule;
use App\Models\FinancerUser;
use App\Models\Module;
use App\Services\Invoicing\CalculateProrataService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
class CalculateProrataServiceTest extends TestCase
{
    use DatabaseTransactions;

    private CalculateProrataService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::store()->flush();
        $this->service = app(CalculateProrataService::class);
    }

    #[Test]
    public function it_returns_full_prorata_when_contract_starts_before_period(): void
    {
        $contractDate = Carbon::parse('2025-01-01');
        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-29');

        $result = $this->service->calculateContractProrata($contractDate, $periodStart, $periodEnd);

        $this->assertSame(1.0, $result);
    }

    #[Test]
    public function it_returns_zero_prorata_when_contract_starts_after_period(): void
    {
        $contractDate = Carbon::parse('2025-03-05');
        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateContractProrata($contractDate, $periodStart, $periodEnd);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function it_calculates_contract_prorata_inside_period_with_two_decimal_precision(): void
    {
        $contractDate = Carbon::parse('2024-02-10');
        $periodStart = Carbon::parse('2024-02-01');
        $periodEnd = Carbon::parse('2024-02-29');

        $result = $this->service->calculateContractProrata($contractDate, $periodStart, $periodEnd);

        $this->assertSame(0.69, $result);
    }

    #[Test]
    public function it_handles_single_day_period_correctly(): void
    {
        $contractDate = Carbon::parse('2025-01-15');
        $periodStart = Carbon::parse('2025-01-15');
        $periodEnd = Carbon::parse('2025-01-15');

        $result = $this->service->calculateContractProrata($contractDate, $periodStart, $periodEnd);

        $this->assertSame(1.0, $result);
    }

    #[Test]
    public function it_returns_prorata_for_beneficiaries_active_entire_period(): void
    {
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2024-01-01'),
            ]],
        ]);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateBeneficiaryProrata($financer->id, $periodStart, $periodEnd);

        $this->assertSame([$user->id => 1.0], $result);
    }

    #[Test]
    public function it_calculates_prorata_for_beneficiary_activated_mid_period(): void
    {
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-02-16'),
            ]],
        ]);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateBeneficiaryProrata($financer->id, $periodStart, $periodEnd);

        $this->assertSame(0.46, $result[$user->id]);
    }

    #[Test]
    public function it_calculates_prorata_for_beneficiary_deactivated_mid_period(): void
    {
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2024-12-01'),
            ]],
        ]);

        FinancerUser::where('financer_id', $financer->id)
            ->where('user_id', $user->id)
            ->update(['to' => Carbon::parse('2025-02-14')]);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateBeneficiaryProrata($financer->id, $periodStart, $periodEnd);

        $this->assertSame(0.50, $result[$user->id]);
    }

    #[Test]
    public function it_calculates_module_prorata_from_audit_activation(): void
    {
        $financer = ModelFactory::createFinancer();
        $module = Module::factory()->create();

        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => Carbon::parse('2025-02-10'),
            'updated_at' => Carbon::parse('2025-02-10'),
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
            'created_at' => Carbon::parse('2025-02-10 08:00:00'),
            'updated_at' => Carbon::parse('2025-02-10 08:00:00'),
        ]);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateModuleProrata(
            $financer->id,
            $module->id,
            $periodStart,
            $periodEnd
        );

        $this->assertInstanceOf(ProrataCalculationDTO::class, $result);
        $this->assertSame(0.68, $result->percentage);
        $this->assertSame('2025-02-10', $result->activationDate);
        $this->assertNull($result->deactivationDate);
        $this->assertSame(19, $result->days);
        $this->assertSame(28, $result->totalDays);
    }

    #[Test]
    public function it_calculates_module_prorata_when_module_is_deactivated(): void
    {
        $financer = ModelFactory::createFinancer();
        $module = Module::factory()->create();

        $financer->modules()->attach($module->id, [
            'active' => false,
            'created_at' => Carbon::parse('2025-02-01'),
            'updated_at' => Carbon::parse('2025-02-20'),
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
            'created_at' => Carbon::parse('2025-02-20 08:00:00'),
            'updated_at' => Carbon::parse('2025-02-20 08:00:00'),
        ]);

        $periodStart = Carbon::parse('2025-02-01');
        $periodEnd = Carbon::parse('2025-02-28');

        $result = $this->service->calculateModuleProrata(
            $financer->id,
            $module->id,
            $periodStart,
            $periodEnd
        );

        $this->assertSame(0.71, $result->percentage);
        $this->assertSame('2025-02-01', $result->activationDate);
        $this->assertSame('2025-02-20', $result->deactivationDate);
        $this->assertSame(20, $result->days);
        $this->assertSame(28, $result->totalDays);
    }
}
