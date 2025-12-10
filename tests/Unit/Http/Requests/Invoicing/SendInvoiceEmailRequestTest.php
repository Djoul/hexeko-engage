<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\SendInvoiceEmailRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class SendInvoiceEmailRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_valid_email_payload(): void
    {
        $data = [
            'email' => 'user@example.com',
            'cc' => ['finance@example.com'],
        ];

        $validator = Validator::make($data, (new SendInvoiceEmailRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_invalid_email_addresses(): void
    {
        $data = [
            'email' => 'not-an-email',
            'cc' => ['also-not-an-email'],
        ];

        $validator = Validator::make($data, (new SendInvoiceEmailRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('email', $validator->errors()->toArray());
        $this->assertArrayHasKey('cc.0', $validator->errors()->toArray());
    }
}
