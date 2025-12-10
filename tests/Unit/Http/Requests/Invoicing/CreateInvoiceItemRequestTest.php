<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\CreateInvoiceItemRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class CreateInvoiceItemRequestTest extends TestCase
{
    #[Test]
    public function it_validates_core_package_item(): void
    {
        $data = [
            'item_type' => 'core_package',
            'module_id' => null,
            'unit_price_htva' => 1200,
            'quantity' => 12,
            'label' => ['fr' => 'Forfait'],
            'description' => ['fr' => 'Description'],
        ];

        $request = new CreateInvoiceItemRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_requires_module_id_for_module_items(): void
    {
        $data = [
            'item_type' => 'module',
            'unit_price_htva' => 800,
            'quantity' => 5,
        ];

        $request = new CreateInvoiceItemRequest;
        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('module_id', $validator->errors()->toArray());
    }
}
