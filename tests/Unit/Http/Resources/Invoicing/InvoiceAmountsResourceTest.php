<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Resources\Invoicing;

use App\DTOs\Invoicing\InvoiceAmountsDTO;
use App\Http\Resources\Invoicing\InvoiceAmountsResource;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('resources')]
class InvoiceAmountsResourceTest extends TestCase
{
    #[Test]
    public function it_transforms_amounts_dto(): void
    {
        $dto = new InvoiceAmountsDTO(1000, 210, 1210, 'EUR');

        $resource = new InvoiceAmountsResource($dto);
        $data = $resource->toArray(Request::create('/test'));

        $this->assertSame(1000, $data['subtotal_htva']);
        $this->assertSame(210, $data['vat_amount']);
        $this->assertSame(1210, $data['total_ttc']);
        $this->assertSame('EUR', $data['currency']);
    }
}
