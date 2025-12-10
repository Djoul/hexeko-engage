<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Actions\Invoicing\GeneratePdfAction;
use App\Actions\Invoicing\SendInvoiceEmailAction;
use App\DTOs\Invoicing\GeneratePdfDTO;
use App\DTOs\Invoicing\SendInvoiceEmailDTO;
use App\Jobs\Invoicing\GenerateFinancerInvoiceJob;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\Module;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;

/**
 * End-to-End tests for complete invoice generation flow
 *
 * Tests the entire invoice lifecycle:
 * 1. Generate invoice from financer data
 * 2. Create PDF document
 * 3. Store in S3/local storage
 * 4. Send email notification with PDF attachment and with exports
 */
#[Group('e2e')]
#[Group('invoicing')]
class InvoiceGenerationFlowTest extends E2ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fake queues and mail for E2E testing
        Queue::fake();
        Mail::fake();
        Storage::fake('local');
    }

    #[Test]
    public function it_generates_complete_invoice_flow_for_financer(): void
    {
        // Arrange: Create test data
        ['financer' => $financer] = $this->createTestFinancerWithDivision();

        $monthYear = now()->subMonth()->format('Y-m');

        // Act: Generate invoice using helper
        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // Assert: Invoice created
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($financer->id, $invoice->financer_id);
        $this->assertEquals($monthYear, $invoice->period);

        // Assert: Invoice has correct structure
        $this->assertNotNull($invoice->invoice_number);
        $this->assertNotNull($invoice->total_amount);
        $this->assertGreaterThan(0, $invoice->invoice_items()->count());

        // Assert: PDF generation job dispatched
        $this->assertJobDispatched(GenerateFinancerInvoiceJob::class);

        // Assert: Email notification sent
        $this->assertEmailSent(InvoiceMail::class);
    }

    #[Test]
    public function it_handles_invoice_generation_with_multiple_items(): void
    {
        // Arrange: Create financer with active modules
        ['division' => $division, 'financer' => $financer] = $this->createTestFinancerWithDivision();

        // Create multiple active modules for the financer
        $modules = Module::factory()->count(3)->create(['is_core' => false]);

        foreach ($modules as $module) {
            $financer->modules()->attach($module->id, [
                'active' => true,
                'price' => 100.00,
            ]);
        }

        $monthYear = now()->subMonth()->format('Y-m');

        // Act: Generate invoice
        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // Assert: Invoice contains all module items
        $this->assertGreaterThanOrEqual(3, $invoice->invoice_items()->count());

        // Assert: Total amount matches sum of items
        $expectedTotal = $invoice->invoice_items()->sum('total_amount');
        $this->assertEquals($expectedTotal, $invoice->total_amount);
    }

    #[Test]
    public function it_generates_pdf_and_stores_in_correct_location(): void
    {
        // Arrange
        ['financer' => $financer] = $this->createTestFinancerWithDivision();
        $monthYear = now()->subMonth()->format('Y-m');

        // Act
        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // Process PDF generation (normally done by job)
        $generatePdfAction = app(GeneratePdfAction::class);
        $pdfPath = $generatePdfAction->execute(
            GeneratePdfDTO::from([
                'invoiceId' => $invoice->id,
            ])
        );

        // Assert: PDF file created
        $this->assertNotNull($pdfPath);
        Storage::disk('local')->assertExists($pdfPath);

        // Assert: PDF is valid (basic check)
        $pdfContent = Storage::disk('local')->get($pdfPath);
        $this->assertStringStartsWith('%PDF', $pdfContent);
    }

    #[Test]
    public function it_sends_email_with_pdf_attachment(): void
    {
        // Arrange
        ['financer' => $financer] = $this->createTestFinancerWithDivision();
        $monthYear = now()->subMonth()->format('Y-m');

        // Act
        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // Process email sending
        $sendEmailAction = app(SendInvoiceEmailAction::class);
        $sendEmailAction->execute(
            SendInvoiceEmailDTO::from([
                'invoiceId' => $invoice->id,
            ])
        );

        // Assert: Email sent to correct recipient
        Mail::assertSent(InvoiceMail::class, function ($mail) use ($financer) {
            return $mail->hasTo($financer->email);
        });
    }

    #[Test]
    public function it_handles_zero_amount_invoice_gracefully(): void
    {
        // Arrange: Financer with no active modules
        ['financer' => $financer] = $this->createTestFinancerWithDivision();
        $monthYear = now()->subMonth()->format('Y-m');

        // Act
        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // Assert: Invoice created but with zero amount
        $this->assertEquals(0, $invoice->total_amount);
        $this->assertEquals(0, $invoice->invoice_items()->count());

        // Assert: No email sent for zero-amount invoices
        Mail::assertNothingSent();
    }
}
