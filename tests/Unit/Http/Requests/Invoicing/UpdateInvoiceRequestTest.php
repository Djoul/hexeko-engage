<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\UpdateInvoiceRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class UpdateInvoiceRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_partial_update_payload(): void
    {
        $data = [
            'due_date' => now()->addDays(15)->toDateString(),
            'notes' => 'Updated notes',
            'metadata' => ['key' => 'value'],
        ];

        $validator = Validator::make($data, (new UpdateInvoiceRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_invalid_due_date(): void
    {
        $data = [
            'due_date' => 'not-a-date',
        ];

        $validator = Validator::make($data, (new UpdateInvoiceRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('due_date', $validator->errors()->toArray());
    }
}
