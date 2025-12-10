<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\MarkInvoiceAsPaidAction;
use App\Aggregates\DivisionBalanceAggregate;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\DivisionBalance;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
class MarkInvoiceAsPaidActionTest extends TestCase
{
    use DatabaseTransactions;

    private MarkInvoiceAsPaidAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(MarkInvoiceAsPaidAction::class);
    }

    #[Test]
    public function it_marks_invoice_as_paid_and_updates_balance(): void
    {
        Carbon::setTestNow('2025-05-05 11:00:00');

        $division = ModelFactory::createDivision();

        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::SENT,
            'recipient_type' => 'division',
            'recipient_id' => $division->id,
            'total_ttc' => 20_000,
        ]);

        DivisionBalanceAggregate::retrieve($division->id)
            ->invoiceGenerated($division->id, $invoice->id, $invoice->total_ttc, Carbon::now())
            ->persist();

        $result = $this->action->execute($invoice->fresh(), 20_000);

        $this->assertInstanceOf(InvoiceDTO::class, $result);

        $updated = $invoice->fresh();
        $this->assertSame(InvoiceStatus::PAID, $updated->status);
        $this->assertTrue(Carbon::parse($updated->paid_at)->equalTo(Carbon::now()));

        $balance = DivisionBalance::where('division_id', $division->id)->firstOrFail();
        $this->assertSame(0, $balance->balance);

        Carbon::setTestNow();
    }

    #[Test]
    public function it_requires_amount_greater_than_zero(): void
    {
        $invoice = Invoice::factory()->sent()->create([
            'total_ttc' => 10_000,
        ]);

        $this->expectException(InvalidArgumentException::class);

        $this->action->execute($invoice, 0);
    }
}
