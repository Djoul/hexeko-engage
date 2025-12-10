<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\InvoiceAmountsDTO;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceAmountsDtoTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_builds_from_invoice_model(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal_htva' => 1000,
            'vat_amount' => 210,
            'total_ttc' => 1210,
            'currency' => 'EUR',
        ]);

        $dto = InvoiceAmountsDTO::fromInvoice($invoice);

        $this->assertSame(1000, $dto->subtotalHtva);
        $this->assertSame(210, $dto->vatAmount);
        $this->assertSame(1210, $dto->totalTtc);
        $this->assertSame('EUR', $dto->currency);
    }

    #[Test]
    public function it_serializes_to_array(): void
    {
        $dto = new InvoiceAmountsDTO(1000, 210, 1210, 'EUR');

        $this->assertSame([
            'subtotal_htva' => 1000,
            'vat_amount' => 210,
            'total_ttc' => 1210,
            'currency' => 'EUR',
        ], $dto->toArray());
    }
}
