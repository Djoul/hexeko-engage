<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Enums\InvoiceStatus;
use App\Http\Requests\Invoicing\ListInvoicesRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class ListInvoicesRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_filters(): void
    {
        $data = [
            'status' => InvoiceStatus::CONFIRMED,
            'recipient_id' => Str::uuid()->toString(),
            'billing_period_start' => now()->toDateString(),
            'per_page' => 50,
            'cursor' => 'encoded-cursor',
        ];

        $validator = Validator::make($data, (new ListInvoicesRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_invalid_status(): void
    {
        $data = [
            'status' => 'invalid-status',
        ];

        $validator = Validator::make($data, (new ListInvoicesRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }
}
