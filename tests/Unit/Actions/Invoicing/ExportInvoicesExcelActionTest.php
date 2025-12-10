<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\ExportInvoicesExcelAction;
use App\Enums\InvoiceStatus;
use App\Models\Division;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;
use ZipArchive;

#[Group('invoicing')]
#[Group('excel')]
class ExportInvoicesExcelActionTest extends TestCase
{
    use DatabaseTransactions;

    private ExportInvoicesExcelAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension is required to generate XLSX exports.');
        }

        Config::set('invoicing.export', [
            'filename_prefix' => 'invoices',
        ]);

        $this->action = $this->app->make(ExportInvoicesExcelAction::class);
    }

    #[Test]
    public function it_streams_filtered_invoices(): void
    {
        Date::setTestNow('2025-05-15 09:00:00');

        /** @var Division $division */
        $division = Division::factory()->create(['name' => 'Target Division']);

        Invoice::factory()->create([
            'invoice_number' => 'INV-TARGET-001',
            'status' => InvoiceStatus::SENT,
            'recipient_id' => $division->id,
            'recipient_type' => Division::class,
            'billing_period_start' => '2025-05-01',
            'billing_period_end' => '2025-05-31',
        ]);

        Invoice::factory()->create([
            'invoice_number' => 'INV-OTHER-001',
            'status' => InvoiceStatus::DRAFT,
            'billing_period_start' => '2025-04-01',
            'billing_period_end' => '2025-04-30',
        ]);

        $response = $this->action->execute([
            'status' => InvoiceStatus::SENT,
            'date_start' => '2025-05-01',
            'date_end' => '2025-05-31',
            'recipient_id' => $division->id,
        ]);

        $this->assertInstanceOf(StreamedResponse::class, $response);

        $rows = $this->extractRows($response);

        $this->assertCount(2, $rows); // headings + 1 row
        $this->assertSame('INV-TARGET-001', $rows[1][0]);
        $this->assertSame(InvoiceStatus::SENT, $rows[1][2]);

        Date::setTestNow();
    }

    #[Test]
    public function it_includes_expected_headings(): void
    {
        $response = $this->action->execute();

        $rows = $this->extractRows($response);

        $expectedHeadings = [
            'Invoice Number',
            'Recipient',
            'Status',
            'Subtotal (HTVA)',
            'VAT Amount',
            'Total (TTC)',
            'Billing Period Start',
            'Billing Period End',
            'Due Date',
        ];

        $this->assertSame($expectedHeadings, $rows[0]);
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function extractRows(StreamedResponse $response): array
    {
        ob_start();
        $response->sendContent();
        /** @var string $binary */
        $binary = ob_get_clean();

        $temp = tmpfile();
        if ($temp === false) {
            $this->fail('Unable to create temporary file for Excel content.');
        }

        $metadata = stream_get_meta_data($temp);
        $path = $metadata['uri'] ?? null;
        if ($path === null) {
            fclose($temp);
            $this->fail('Unable to resolve temporary file path.');
        }

        file_put_contents($path, $binary);

        $spreadsheet = IOFactory::load($path);
        $rows = $spreadsheet->getActiveSheet()->toArray();

        fclose($temp);

        return $rows;
    }
}
