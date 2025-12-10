<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\MarkInvoiceAsPaidRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class MarkInvoiceAsPaidRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_optional_amount_paid(): void
    {
        $validator = Validator::make(['amount_paid' => 5000], (new MarkInvoiceAsPaidRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_negative_amount(): void
    {
        $validator = Validator::make(['amount_paid' => -1], (new MarkInvoiceAsPaidRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('amount_paid', $validator->errors()->toArray());
    }
}
