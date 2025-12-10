<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoicing\BulkUpdateInvoiceStatusRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class BulkUpdateInvoiceStatusRequestTest extends TestCase
{
    #[Test]
    public function it_validates_invoice_ids_and_status(): void
    {
        $data = [
            'invoice_ids' => [Str::uuid()->toString(), Str::uuid()->toString()],
            'status' => InvoiceStatus::CONFIRMED,
        ];

        $validator = Validator::make($data, (new BulkUpdateInvoiceStatusRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_requires_invoice_ids_array(): void
    {
        $validator = Validator::make(['status' => InvoiceStatus::PAID], (new BulkUpdateInvoiceStatusRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('invoice_ids', $validator->errors()->toArray());
    }
}
