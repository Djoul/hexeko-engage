<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\GenerateMonthlyInvoicesAction;
use App\DTOs\Invoicing\InvoiceBatchDTO;
use App\Enums\InvoiceType;
use App\Models\FinancerModule;
use App\Models\Invoice;
use App\Models\InvoiceGenerationBatch;
use App\Models\Module;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(tables: ['invoices'], scope: 'test')]
#[Group('invoicing')]
class GenerateMonthlyInvoicesActionTest extends TestCase
{
    use DatabaseTransactions;

    private GenerateMonthlyInvoicesAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(GenerateMonthlyInvoicesAction::class);
    }

    #[Test]
    public function it_generates_invoices_for_division_and_financers(): void
    {
        Carbon::setTestNow('2025-05-01 07:00:00');

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

        // Beneficiaries - create exactly like the working GenerateDivisionInvoiceActionTest
        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-04-15'),
            ]],
        ]);

        ModelFactory::createUser([
            'financers' => [[
                'financer' => $financer,
                'active' => true,
                'from' => Carbon::parse('2025-05-01'),
            ]],
        ]);

        /** @var Module $module */
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

        // Activation audit for module
        $pivotId = DB::table('financer_module')
            ->where('financer_id', $financer->id)
            ->where('module_id', $module->id)
            ->value('id');

        $this->assertNotNull($pivotId, 'financer_module pivot record should exist');
        $this->assertIsString($pivotId, 'pivot id should be a string (UUID)');

        DB::table('audits')->insert([
            'auditable_type' => FinancerModule::class,
            'auditable_id' => $pivotId,
            'event' => 'updated',
            'old_values' => json_encode(['active' => false]),
            'new_values' => json_encode(['active' => true]),
            'created_at' => Carbon::parse('2025-04-01 00:00:00'),
            'updated_at' => Carbon::parse('2025-04-01 00:00:00'),
        ]);

        $result = $this->action->execute(
            monthYear: '2025-05',
            divisionId: $division->id,
            financerId: null,
            dryRun: false,
        );

        $this->assertInstanceOf(InvoiceBatchDTO::class, $result);

        $batch = InvoiceGenerationBatch::where('batch_id', $result->batchId)->firstOrFail();
        $this->assertSame('completed', $batch->status);
        $this->assertSame(2, $batch->total_invoices);
        $this->assertSame(2, $batch->completed_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertNotNull($batch->completed_at);

        $invoices = Invoice::orderBy('created_at')->get();
        $this->assertCount(2, $invoices);

        $divisionInvoice = $invoices->firstWhere('invoice_type', InvoiceType::HEXEKO_TO_DIVISION);
        $this->assertNotNull($divisionInvoice);
        $this->assertSame($division->id, $divisionInvoice->recipient_id);

        $financerInvoice = $invoices->firstWhere('invoice_type', InvoiceType::DIVISION_TO_FINANCER);
        $this->assertNotNull($financerInvoice);
        $this->assertSame($financer->id, $financerInvoice->recipient_id);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_handles_dry_run_without_creating_invoices(): void
    {
        $division = ModelFactory::createDivision();
        ModelFactory::createFinancer(['division_id' => $division->id]);

        $result = $this->action->execute(
            monthYear: '2025-05',
            divisionId: null,
            financerId: null,
            dryRun: true,
        );

        $this->assertInstanceOf(InvoiceBatchDTO::class, $result);
        $this->assertSame('dry_run', $result->status);
        $this->assertSame(0, Invoice::count());
    }
}
