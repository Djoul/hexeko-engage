<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\ConfirmInvoiceRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class ConfirmInvoiceRequestTest extends TestCase
{
    #[Test]
    public function it_allows_empty_payload(): void
    {
        $validator = Validator::make([], (new ConfirmInvoiceRequest)->rules());

        $this->assertTrue($validator->passes());
    }
}
