<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Invoicing;

use App\Models\Invoice;
use App\Services\Invoicing\GenerateInvoiceNumberService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
class GenerateInvoiceNumberServiceTest extends TestCase
{
    use DatabaseTransactions;

    private GenerateInvoiceNumberService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(GenerateInvoiceNumberService::class);
    }

    #[Test]
    public function invoice_sequences_table_exists_for_generation(): void
    {
        $this->assertTrue(Schema::hasTable('invoice_sequences'));
    }

    #[Test]
    public function it_generates_incremental_invoice_numbers_with_default_pattern(): void
    {
        $date = Carbon::parse('2025-03-05');

        $first = $this->service->generate('hexeko_to_division', $date);
        $second = $this->service->generate('hexeko_to_division', $date);

        $this->assertSame('HEXEKO_TO_DIVISION-2025-000001', $first);
        $this->assertSame('HEXEKO_TO_DIVISION-2025-000002', $second);

        $sequenceRow = DB::table('invoice_sequences')
            ->where('invoice_type', 'hexeko_to_division')
            ->where('year', '2025')
            ->first();

        $this->assertNotNull($sequenceRow);
        $this->assertSame(2, (int) $sequenceRow->sequence);
    }

    #[Test]
    public function it_skips_existing_invoice_numbers_to_ensure_uniqueness(): void
    {
        $date = Carbon::parse('2025-01-15');
        $existingNumber = 'DIVISION_TO_FINANCER-2025-000001';

        Invoice::factory()->create([
            'invoice_number' => $existingNumber,
        ]);

        $generated = $this->service->generate('division_to_financer', $date);

        $this->assertSame('DIVISION_TO_FINANCER-2025-000002', $generated);
    }

    #[Test]
    public function it_respects_custom_pattern_configuration(): void
    {
        Config::set('invoicing.invoice_number.pattern', 'INV-{year}-{sequence}-{type}');
        Config::set('invoicing.invoice_number.sequence_padding', 4);
        Config::set('invoicing.invoice_number.type_mapping.division_to_financer', 'DF');

        $service = app(GenerateInvoiceNumberService::class);
        $date = Carbon::parse('2026-07-01');

        $number = $service->generate('division_to_financer', $date);

        $this->assertSame('INV-2026-0001-DF', $number);
    }
}
