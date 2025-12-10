<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\GenerateFinancerInvoiceAction;
use App\Aggregates\InvoiceGenerationAggregate;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceType;
use App\Models\Financer;
use App\Models\FinancerModule;
use App\Models\Invoice;
use App\Models\InvoiceGenerationBatch;
use App\Models\Module;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
class GenerateFinancerInvoiceActionTest extends TestCase
{
    use DatabaseTransactions;

    private GenerateFinancerInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(GenerateFinancerInvoiceAction::class);
    }

    #[Test]
    public function it_generates_financer_invoice_with_core_and_module_items(): void
    {
        Carbon::setTestNow('2025-05-01 09:00:00');

        $division = ModelFactory::createDivision([
            'core_package_price' => 4_000,
            'country' => 'FR',
            'currency' => 'EUR',
            'vat_rate' => 20.00,
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'core_package_price' => 6_000,
        ]);

        // Beneficiaries
        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-04-01'),
            ]],
        ]);

        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-03-15'),
            ]],
        ]);

        $module = Module::factory()->create();
        $division->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 1_500,
        ]);

        $financer->modules()->attach($module->id, [
            'active' => true,
            'price_per_beneficiary' => 2_000,
            'created_at' => Carbon::parse('2025-03-01'),
            'updated_at' => Carbon::parse('2025-03-01'),
        ]);

        // Simulate activation audit for module
        $pivotId = DB::table('financer_module')
            ->where('financer_id', $financer->id)
            ->where('module_id', $module->id)
            ->value('id');

        DB::table('audits')->insert([
            'auditable_type' => FinancerModule::class,
            'auditable_id' => (string) $pivotId,
            'event' => 'updated',
            'old_values' => json_encode(['active' => false]),
            'new_values' => json_encode(['active' => true]),
            'created_at' => Carbon::parse('2025-04-01 00:00:00'),
            'updated_at' => Carbon::parse('2025-04-01 00:00:00'),
        ]);

        $batchId = Str::uuid()->toString();
        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchStarted($batchId, '2025-05', 1, Carbon::parse('2025-05-01 09:00:00'))
            ->persist();

        $result = $this->action->execute(
            financerId: $financer->id,
            monthYear: '2025-05',
            batchId: $batchId,
        );

        $this->assertInstanceOf(InvoiceDTO::class, $result);

        $invoice = Invoice::findOrFail($result->id);
        $this->assertSame($financer->id, $invoice->recipient_id);
        $this->assertSame(Financer::class, $invoice->recipient_type);
        $this->assertSame(InvoiceType::DIVISION_TO_FINANCER, $invoice->invoice_type);
        $this->assertSame(16_000, $invoice->subtotal_htva);
        $this->assertSame(3_200, $invoice->vat_amount);
        $this->assertSame(19_200, $invoice->total_ttc);

        $items = $invoice->items()->get();
        $this->assertCount(2, $items);

        $coreItem = $items->firstWhere('item_type', 'core_package');
        $this->assertNotNull($coreItem);
        $this->assertSame(6_000, $coreItem->unit_price_htva);
        $this->assertSame(2, $coreItem->quantity);

        $moduleItem = $items->firstWhere('item_type', 'module');
        $this->assertNotNull($moduleItem);
        $this->assertSame($module->id, $moduleItem->module_id);
        $this->assertSame(2_000, $moduleItem->unit_price_htva);
        $this->assertSame(2, $moduleItem->quantity);

        $batch = InvoiceGenerationBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame(1, $batch->completed_count);
        $this->assertSame(0, $batch->failed_count);

        Carbon::setTestNow();
    }
}
