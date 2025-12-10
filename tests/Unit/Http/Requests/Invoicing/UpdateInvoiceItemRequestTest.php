<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\UpdateInvoiceItemRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class UpdateInvoiceItemRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_partial_item_updates(): void
    {
        $data = [
            'unit_price_htva' => 900,
            'quantity' => 3,
            'label' => ['fr' => 'Updated'],
        ];

        $validator = Validator::make($data, (new UpdateInvoiceItemRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_invalid_quantity(): void
    {
        $data = [
            'quantity' => 0,
        ];

        $validator = Validator::make($data, (new UpdateInvoiceItemRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('quantity', $validator->errors()->toArray());
    }
}
