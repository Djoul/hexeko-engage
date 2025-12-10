<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\ConfirmInvoiceAction;
use App\DTOs\Invoicing\InvoiceDTO;
use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use LogicException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class ConfirmInvoiceActionTest extends TestCase
{
    use DatabaseTransactions;

    private ConfirmInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ConfirmInvoiceAction::class);
    }

    #[Test]
    public function it_confirms_a_draft_invoice(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => InvoiceStatus::DRAFT,
            'confirmed_at' => null,
        ]);

        $result = $this->action->execute($invoice->fresh());

        $this->assertInstanceOf(InvoiceDTO::class, $result);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'status' => InvoiceStatus::CONFIRMED,
        ]);
        $this->assertNotNull($invoice->fresh()->confirmed_at);
    }

    #[Test]
    public function it_rejects_non_draft_invoices(): void
    {
        $invoice = Invoice::factory()->paid()->create();

        $this->expectException(LogicException::class);

        $this->action->execute($invoice);
    }
}
