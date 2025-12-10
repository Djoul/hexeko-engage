<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\GenerateDivisionInvoiceAction;
use App\Aggregates\InvoiceGenerationAggregate;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Invoice;
use App\Models\InvoiceGenerationBatch;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
class GenerateDivisionInvoiceActionTest extends TestCase
{
    use DatabaseTransactions;

    private GenerateDivisionInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(GenerateDivisionInvoiceAction::class);
    }

    #[Test]
    public function it_generates_division_invoice_and_updates_projection(): void
    {
        Carbon::setTestNow('2025-05-01 08:00:00');

        $division = ModelFactory::createDivision([
            'core_package_price' => 5_000,
            'country' => 'FR',
            'currency' => 'EUR',
            'vat_rate' => 20.00,
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'core_package_price' => null,
        ]);

        // Two active beneficiaries during the month
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

        $batchId = Str::uuid()->toString();

        InvoiceGenerationAggregate::retrieve($batchId)
            ->batchStarted($batchId, '2025-05', 1, Carbon::parse('2025-05-01 08:00:00'))
            ->persist();

        $result = $this->action->execute(
            divisionId: $division->id,
            monthYear: '2025-05',
            batchId: $batchId,
        );

        $this->assertInstanceOf(InvoiceDTO::class, $result);

        $invoice = Invoice::findOrFail($result->id);
        $this->assertSame($division->id, $invoice->recipient_id);
        $this->assertSame(Division::class, $invoice->recipient_type);
        $this->assertSame(InvoiceType::HEXEKO_TO_DIVISION, $invoice->invoice_type);
        $this->assertNotNull($invoice->invoice_number);
        $this->assertSame(10_000, $invoice->subtotal_htva);
        $this->assertSame(2_000, $invoice->vat_amount);
        $this->assertSame(12_000, $invoice->total_ttc);

        $this->assertDatabaseHas('invoice_items', [
            'invoice_id' => $invoice->id,
            'item_type' => 'core_package',
            'quantity' => 2,
        ]);

        $this->assertDatabaseHas('division_balances', [
            'division_id' => $division->id,
            'balance' => 12_000,
        ]);

        $batch = InvoiceGenerationBatch::where('batch_id', $batchId)->firstOrFail();
        $this->assertSame(1, $batch->completed_count);
        $this->assertSame(0, $batch->failed_count);
        $this->assertSame('in_progress', $batch->status);

        Carbon::setTestNow();
    }
}
