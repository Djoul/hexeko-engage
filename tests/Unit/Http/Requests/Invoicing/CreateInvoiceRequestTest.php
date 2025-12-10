<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\CreateInvoiceRequest;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class CreateInvoiceRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_payload(): void
    {
        $data = [
            'recipient_type' => 'division',
            'recipient_id' => Str::uuid()->toString(),
            'billing_period_start' => now()->toDateString(),
            'billing_period_end' => now()->addMonthNoOverflow()->toDateString(),
            'vat_rate' => '21.00',
            'currency' => 'EUR',
            'items' => [
                [
                    'item_type' => 'core_package',
                    'module_id' => null,
                    'unit_price_htva' => 1000,
                    'quantity' => 10,
                    'label' => ['fr' => 'Forfait de base'],
                    'description' => ['fr' => 'Description'],
                ],
            ],
        ];

        $request = new CreateInvoiceRequest;
        $validator = Validator::make($data, $request->rules());
        $request->withValidator($validator);

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_requires_recipient_information(): void
    {
        $request = new CreateInvoiceRequest;
        $validator = Validator::make([], $request->rules());
        $request->withValidator($validator);

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('recipient_type', $validator->errors()->toArray());
        $this->assertArrayHasKey('recipient_id', $validator->errors()->toArray());
    }
}
