<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Actions\Invoicing\SendInvoiceEmailAction;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;
use Tests\Helpers\Facades\ModelFactory;

/**
 * E2E-013: Invoice Email with Excel Exports
 *
 * Validates invoice email with detailed Excel exports:
 * ✅ PDF attachment (existing behavior)
 * ✅ User Billing Details Excel export attached
 * ✅ Module Activation Excel export attached
 * ✅ Optional export inclusion (flags)
 * ✅ Email queued with all attachments
 * ✅ Correct file names for Excel exports
 */
#[Group('e2e')]
#[Group('e2e-critical')]
#[Group('invoicing')]
#[Group('exports')]
class InvoiceEmailWithExportsTest extends E2ETestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fake storage for PDF exports
        Storage::fake('s3-local');
        Storage::fake('local');

        // Fake mail
        Mail::fake();
    }

    #[Test]
    public function e2e_013_it_sends_email_with_all_excel_exports(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with users and modules
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision([], [
            'core_package_price' => 300000, // €3000.00
        ]);

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_type' => 'division_to_financer',
            'status' => 'confirmed',
            'invoice_number' => 'FAC-2025-00456',
            'total_ttc' => 500000,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
            'confirmed_at' => now(),
        ]);

        // Create 2 users for user billing export
        ModelFactory::createUser([
            'email' => 'user1@test.com',
            'first_name' => 'Alice',
            'last_name' => 'Wonder',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => '2025-10-01'],
            ],
        ]);

        ModelFactory::createUser([
            'email' => 'user2@test.com',
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => '2025-10-15'],
            ],
        ]);

        // Create 2 modules for module activation export
        $module1 = Module::factory()->create(['name' => ['en' => 'Wellness Module']]);
        $financer->modules()->attach($module1->id, [
            'active' => true,
            'created_at' => '2025-10-01',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module1->id,
            'unit_price_htva' => 150000,
            'quantity' => 1,
            'subtotal_htva' => 150000,
            'vat_rate' => 21.00,
            'vat_amount' => 31500,
            'total_ttc' => 181500,
        ]);

        $module2 = Module::factory()->create(['name' => ['en' => 'Mobility Module']]);
        $financer->modules()->attach($module2->id, [
            'active' => true,
            'created_at' => '2025-10-15',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module2->id,
            'unit_price_htva' => 200000,
            'quantity' => 1,
            'subtotal_htva' => 200000,
            'vat_rate' => 21.00,
            'vat_amount' => 42000,
            'total_ttc' => 242000,
        ]);

        // Create admin contact
        $financerAdmin = ModelFactory::createUser([
            'email' => 'admin@financer.com',
            'first_name' => 'Admin',
            'last_name' => 'User',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $sendEmailAction = app(SendInvoiceEmailAction::class);

        // ============================================================
        // ACT: Send email with all Excel exports
        // ============================================================

        $sendEmailAction->execute(
            invoiceId: $invoice->id,
            email: $financerAdmin->email,
            includeUserBilling: true,
            includeModuleActivation: true
        );

        // ============================================================
        // ASSERT: Verify email queued with all attachments
        // ============================================================

        // ✅ 1. Email queued
        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail) use ($financerAdmin, $invoice): bool {
            // ✅ 2. Correct recipient
            if (! $mail->hasTo($financerAdmin->email)) {
                return false;
            }

            // ✅ 3. Invoice attached
            if ($mail->invoice->id !== $invoice->id) {
                return false;
            }

            // ✅ 4. Excel exports attached
            if (count($mail->excelExports) !== 2) {
                return false;
            }

            // ✅ 5. User billing export present
            if (! isset($mail->excelExports['user-billing'])) {
                return false;
            }

            // ✅ 6. Module activation export present
            if (! isset($mail->excelExports['module-activation'])) {
                return false;
            }

            // ✅ 7. Excel content not empty
            if (strlen($mail->excelExports['user-billing']) < 100) {
                return false;
            }

            if (strlen($mail->excelExports['module-activation']) < 100) {
                return false;
            }

            // ✅ 8. Excel content starts with PK signature (ZIP format)
            if (! str_starts_with($mail->excelExports['user-billing'], 'PK')) {
                return false;
            }

            return str_starts_with($mail->excelExports['module-activation'], 'PK');
        });

        // ✅ 9. Invoice.sent_at updated
        $invoice->refresh();
        $this->assertNotNull($invoice->sent_at);

        // ✅ 10. PDF exists in storage
        $expectedPath = "invoices/pdf/{$invoice->id}.pdf";
        Storage::disk('s3-local')->assertExists($expectedPath);
    }

    #[Test]
    public function e2e_013b_it_sends_email_with_only_user_billing_export(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with only users
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision([], [
            'core_package_price' => 200000,
        ]);

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_type' => 'division_to_financer',
            'status' => 'confirmed',
            'invoice_number' => 'FAC-2025-00457',
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        ModelFactory::createUser([
            'email' => 'user@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => '2025-10-01'],
            ],
        ]);

        $admin = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $sendEmailAction = app(SendInvoiceEmailAction::class);

        // ============================================================
        // ACT: Send email with only user billing export
        // ============================================================

        $sendEmailAction->execute(
            invoiceId: $invoice->id,
            email: $admin->email,
            includeUserBilling: true,
            includeModuleActivation: false
        );

        // ============================================================
        // ASSERT: Verify only user billing export attached
        // ============================================================

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            // Should have only 1 Excel export
            if (count($mail->excelExports) !== 1) {
                return false;
            }

            // Should be user-billing export
            return isset($mail->excelExports['user-billing']);
        });
    }

    #[Test]
    public function e2e_013c_it_sends_email_without_excel_exports(): void
    {
        // ============================================================
        // ARRANGE: Create invoice
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision();

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_type' => 'division_to_financer',
            'status' => 'confirmed',
            'invoice_number' => 'FAC-2025-00458',
        ]);

        $admin = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $sendEmailAction = app(SendInvoiceEmailAction::class);

        // ============================================================
        // ACT: Send email without Excel exports
        // ============================================================

        $sendEmailAction->execute(
            invoiceId: $invoice->id,
            email: $admin->email,
            includeUserBilling: false,
            includeModuleActivation: false
        );

        // ============================================================
        // ASSERT: Verify no Excel exports attached
        // ============================================================

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            // Should have no Excel exports
            return count($mail->excelExports) === 0;
        });
    }

    #[Test]
    public function e2e_013d_it_skips_excel_exports_for_non_financer_invoices(): void
    {
        // ============================================================
        // ARRANGE: Create division invoice (not financer invoice)
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision();

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Division', // Division invoice
            'recipient_id' => $division->id,
            'issuer_type' => 'App\\Models\\Financer',
            'issuer_id' => $financer->id,
            'invoice_type' => 'hexeko_to_division',
            'status' => 'confirmed',
            'invoice_number' => 'FAC-2025-00459',
        ]);

        $sendEmailAction = app(SendInvoiceEmailAction::class);

        // ============================================================
        // ACT: Send email (should skip Excel exports automatically)
        // ============================================================

        $sendEmailAction->execute(
            invoiceId: $invoice->id,
            email: 'division@test.com',
            includeUserBilling: true,
            includeModuleActivation: true
        );

        // ============================================================
        // ASSERT: Verify no Excel exports for division invoice
        // ============================================================

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            // Should have no Excel exports (division invoices don't have users/modules)
            return count($mail->excelExports) === 0;
        });
    }
}
