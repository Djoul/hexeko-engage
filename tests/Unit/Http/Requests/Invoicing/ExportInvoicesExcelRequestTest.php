<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\Invoicing;

use App\Http\Requests\Invoicing\ExportInvoicesExcelRequest;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('requests')]
class ExportInvoicesExcelRequestTest extends TestCase
{
    #[Test]
    public function it_accepts_empty_filters(): void
    {
        $validator = Validator::make([], (new ExportInvoicesExcelRequest)->rules());

        $this->assertTrue($validator->passes());
    }

    #[Test]
    public function it_rejects_invalid_date_range(): void
    {
        $data = [
            'date_start' => 'invalid-date',
            'date_end' => 'another-invalid',
        ];

        $validator = Validator::make($data, (new ExportInvoicesExcelRequest)->rules());

        $this->assertFalse($validator->passes());
        $this->assertArrayHasKey('date_start', $validator->errors()->toArray());
        $this->assertArrayHasKey('date_end', $validator->errors()->toArray());
    }
}
