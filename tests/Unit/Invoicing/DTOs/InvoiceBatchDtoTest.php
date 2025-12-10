<?php

declare(strict_types=1);

namespace Tests\Unit\Invoicing\DTOs;

use App\DTOs\Invoicing\InvoiceBatchDTO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class InvoiceBatchDtoTest extends TestCase
{
    #[Test]
    public function it_serializes_batch_information(): void
    {
        $dto = new InvoiceBatchDTO(
            batchId: 'batch-2025-03',
            monthYear: '2025-03',
            totalInvoices: 120,
            status: 'processing',
        );

        $payload = $dto->toArray();

        $this->assertSame('batch-2025-03', $payload['batch_id']);
        $this->assertSame(120, $payload['total_invoices']);
        $this->assertSame('processing', $payload['status']);
    }
}
