<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\InvoiceItemType;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceSeeder extends Seeder
{
    private const MIN_INVOICES_PER_ENTITY = 10;

    private const MAX_INVOICES_PER_ENTITY = 15;

    private const CORE_MIN_PRICE_CENTS = 100; // 1€

    private const CORE_MAX_PRICE_CENTS = 200; // 2€

    private const MODULE_MIN_PRICE_CENTS = 100; // 1€

    private const MODULE_MAX_PRICE_CENTS = 150; // 1.50€

    private const MIN_USERS = 100;

    private const MAX_USERS = 300;

    private const VAT_RATE = '21.00';

    public function run(): void
    {
        $this->command->info('🧹 Cleaning existing invoices...');
        DB::table('invoice_items')->delete();
        DB::table('invoices')->delete();

        $divisions = Division::all();
        $financers = Financer::all();
        $nonCoreModules = Module::where('is_core', false)->get();

        if ($divisions->isEmpty()) {
            $this->command->warn('⚠️  No divisions found. Please seed divisions first.');

            return;
        }

        if ($financers->isEmpty()) {
            $this->command->warn('⚠️  No financers found. Please seed financers first.');

            return;
        }

        $this->command->info("📊 Found {$divisions->count()} divisions and {$financers->count()} financers");
        $this->command->info("📦 Found {$nonCoreModules->count()} non-core modules");

        // Generate Hexeko → Division invoices
        $this->command->info('');
        $this->command->info('💼 Generating Hexeko → Division invoices...');
        $hexekoInvoicesCount = 0;

        foreach ($divisions as $division) {
            $count = $this->generateInvoicesForDivision($division, $nonCoreModules);
            $hexekoInvoicesCount += $count;
            $this->command->info("  ✅ Generated {$count} invoices for division: {$division->name}");
        }

        // Generate Division → Financer invoices
        $this->command->info('');
        $this->command->info('🏢 Generating Division → Financer invoices...');
        $financerInvoicesCount = 0;

        foreach ($financers as $financer) {
            $count = $this->generateInvoicesForFinancer($financer, $nonCoreModules);
            $financerInvoicesCount += $count;
            $this->command->info("  ✅ Generated {$count} invoices for financer: {$financer->name}");
        }

        $this->command->info('');
        $this->command->info('✅ Invoice seeding completed!');
        $this->command->info("   📊 Total Hexeko → Division invoices: {$hexekoInvoicesCount}");
        $this->command->info("   📊 Total Division → Financer invoices: {$financerInvoicesCount}");
        $this->command->info('   📊 Grand total: '.($hexekoInvoicesCount + $financerInvoicesCount).' invoices');
    }

    private function generateInvoicesForDivision(Division $division, $nonCoreModules): int
    {
        $invoiceCount = rand(self::MIN_INVOICES_PER_ENTITY, self::MAX_INVOICES_PER_ENTITY);
        $startDate = Carbon::now()->subMonths($invoiceCount);

        for ($i = 0; $i < $invoiceCount; $i++) {
            $billingPeriodStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $billingPeriodEnd = $billingPeriodStart->copy()->endOfMonth();

            // Determine status based on age (older invoices are paid/overdue, recent ones are pending/sent/confirmed)
            $status = $this->determineInvoiceStatus($i, $invoiceCount);

            // Generate consistent user count for all items in this invoice
            $userCount = rand(self::MIN_USERS, self::MAX_USERS);

            $invoice = $this->createInvoice(
                type: InvoiceType::HEXEKO_TO_DIVISION,
                issuerType: 'App\Models\Division', // Hexeko is issuer (represented as Division for structure)
                issuerId: $division->id,
                recipientType: 'App\Models\Division',
                recipientId: $division->id,
                billingPeriodStart: $billingPeriodStart,
                billingPeriodEnd: $billingPeriodEnd,
                status: $status
            );

            // Create items for this invoice
            $this->createInvoiceItems($invoice, $nonCoreModules, $userCount);
        }

        return $invoiceCount;
    }

    private function generateInvoicesForFinancer(Financer $financer, $nonCoreModules): int
    {
        $invoiceCount = rand(self::MIN_INVOICES_PER_ENTITY, self::MAX_INVOICES_PER_ENTITY);
        $startDate = Carbon::now()->subMonths($invoiceCount);

        for ($i = 0; $i < $invoiceCount; $i++) {
            $billingPeriodStart = $startDate->copy()->addMonths($i)->startOfMonth();
            $billingPeriodEnd = $billingPeriodStart->copy()->endOfMonth();

            // Determine status based on age
            $status = $this->determineInvoiceStatus($i, $invoiceCount);

            // Generate consistent user count for all items in this invoice
            $userCount = rand(self::MIN_USERS, self::MAX_USERS);

            $invoice = $this->createInvoice(
                type: InvoiceType::DIVISION_TO_FINANCER,
                issuerType: 'App\Models\Division',
                issuerId: $financer->division_id,
                recipientType: 'App\Models\Financer',
                recipientId: $financer->id,
                billingPeriodStart: $billingPeriodStart,
                billingPeriodEnd: $billingPeriodEnd,
                status: $status
            );

            // Create items for this invoice
            $this->createInvoiceItems($invoice, $nonCoreModules, $userCount);
        }

        return $invoiceCount;
    }

    private function createInvoice(
        string $type,
        string $issuerType,
        string $issuerId,
        string $recipientType,
        string $recipientId,
        Carbon $billingPeriodStart,
        Carbon $billingPeriodEnd,
        string $status
    ): Invoice {
        $invoice = new Invoice;
        $invoice->invoice_type = $type;
        $invoice->issuer_type = $issuerType;
        $invoice->issuer_id = $issuerId;
        $invoice->recipient_type = $recipientType;
        $invoice->recipient_id = $recipientId;
        $invoice->billing_period_start = $billingPeriodStart;
        $invoice->billing_period_end = $billingPeriodEnd;
        $invoice->status = $status;
        $invoice->currency = 'EUR';

        // Set dates based on status
        $this->setInvoiceDates($invoice, $billingPeriodEnd, $status);

        // Generate invoice number
        $invoice->invoice_number = $this->generateInvoiceNumber($type, $billingPeriodStart);

        // Set due date
        $invoice->due_date = $billingPeriodEnd->copy()->addDays(30);

        // Amounts will be calculated after items are created
        $invoice->subtotal_htva = 0;
        $invoice->vat_rate = self::VAT_RATE;
        $invoice->vat_amount = 0;
        $invoice->total_ttc = 0;

        $invoice->save();

        return $invoice;
    }

    private function createInvoiceItems(Invoice $invoice, $nonCoreModules, int $userCount): void
    {
        $totalSubtotal = 0;

        // 1. Create core package item
        $coreUnitPrice = rand(self::CORE_MIN_PRICE_CENTS, self::CORE_MAX_PRICE_CENTS);
        $coreSubtotal = $coreUnitPrice * $userCount;
        $totalSubtotal += $coreSubtotal;

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'item_type' => InvoiceItemType::CORE_PACKAGE,
            'label' => [
                'fr' => 'Offre Core',
                'en' => 'Core Package',
            ],
            'description' => [
                'fr' => 'Package de base incluant les modules essentiels',
                'en' => 'Base package including essential modules',
            ],
            'beneficiaries_count' => $userCount,
            'unit_price_htva' => $coreUnitPrice,
            'quantity' => $userCount,
            'subtotal_htva' => $coreSubtotal,
            'vat_rate' => self::VAT_RATE,
            'vat_amount' => (int) round($coreSubtotal * 0.21),
            'total_ttc' => $coreSubtotal + (int) round($coreSubtotal * 0.21),
            'prorata_percentage' => '100.00',
            'prorata_days' => $invoice->billing_period_start->daysInMonth,
            'total_days' => $invoice->billing_period_start->daysInMonth,
        ]);

        // 2. Create items for non-core modules
        foreach ($nonCoreModules as $module) {
            $moduleUnitPrice = rand(self::MODULE_MIN_PRICE_CENTS, self::MODULE_MAX_PRICE_CENTS);
            $moduleSubtotal = $moduleUnitPrice * $userCount;
            $totalSubtotal += $moduleSubtotal;

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_type' => InvoiceItemType::MODULE,
                'module_id' => $module->id,
                'label' => $module->name,
                'description' => $module->description,
                'beneficiaries_count' => $userCount,
                'unit_price_htva' => $moduleUnitPrice,
                'quantity' => $userCount,
                'subtotal_htva' => $moduleSubtotal,
                'vat_rate' => self::VAT_RATE,
                'vat_amount' => (int) round($moduleSubtotal * 0.21),
                'total_ttc' => $moduleSubtotal + (int) round($moduleSubtotal * 0.21),
                'prorata_percentage' => '100.00',
                'prorata_days' => $invoice->billing_period_start->daysInMonth,
                'total_days' => $invoice->billing_period_start->daysInMonth,
            ]);
        }

        // 3. Update invoice totals
        $totalVat = (int) round($totalSubtotal * 0.21);
        $invoice->update([
            'subtotal_htva' => $totalSubtotal,
            'vat_amount' => $totalVat,
            'total_ttc' => $totalSubtotal + $totalVat,
        ]);
    }

    private function determineInvoiceStatus(int $invoiceIndex, int $totalInvoices): string
    {
        // Last 2 invoices: recent statuses (confirmed, sent, or draft)
        if ($invoiceIndex >= $totalInvoices - 2) {
            return match (rand(1, 3)) {
                1 => InvoiceStatus::CONFIRMED,
                2 => InvoiceStatus::SENT,
                default => InvoiceStatus::DRAFT,
            };
        }

        // Middle invoices: sent
        if ($invoiceIndex >= $totalInvoices - 4) {
            return InvoiceStatus::SENT;
        }

        // Older invoices: paid or overdue
        return rand(1, 10) <= 8 ? InvoiceStatus::PAID : InvoiceStatus::OVERDUE;
    }

    private function setInvoiceDates(Invoice $invoice, Carbon $billingPeriodEnd, string $status): void
    {
        switch ($status) {
            case InvoiceStatus::PAID:
                $invoice->confirmed_at = $billingPeriodEnd->copy()->addDays(2);
                $invoice->sent_at = $invoice->confirmed_at->copy()->addDays(1);
                $invoice->paid_at = $invoice->sent_at->copy()->addDays(rand(3, 15));
                break;

            case InvoiceStatus::OVERDUE:

            case InvoiceStatus::SENT:
                $invoice->confirmed_at = $billingPeriodEnd->copy()->addDays(2);
                $invoice->sent_at = $invoice->confirmed_at->copy()->addDays(1);
                // No paid_at for overdue
                break;

            case InvoiceStatus::CONFIRMED:
                $invoice->confirmed_at = $billingPeriodEnd->copy()->addDays(2);
                break;

            case InvoiceStatus::DRAFT:
                // No dates for draft
                break;
        }
    }

    private function generateInvoiceNumber(string $type, Carbon $date): string
    {
        $prefix = $type === InvoiceType::HEXEKO_TO_DIVISION ? 'HEX' : 'DIV';
        $year = $date->format('Y');
        $month = $date->format('m');
        $random = str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}{$month}-{$random}";
    }
}
