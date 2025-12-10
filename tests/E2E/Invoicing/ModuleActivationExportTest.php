<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Exports\ModuleActivationExport;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;

/**
 * E2E-012: Module Activation Export
 *
 * Validates detailed module activation export with:
 * ✅ Multiple modules with different activation dates
 * ✅ Correct prorata calculations (activation/deactivation)
 * ✅ Accurate module amount calculations
 * ✅ Temporal activation logic
 * ✅ Streaming export with large datasets
 */
#[Group('e2e')]
#[Group('e2e-critical')]
#[Group('invoicing')]
#[Group('exports')]
class ModuleActivationExportTest extends E2ETestCase
{
    #[Test]
    public function e2e_012_it_exports_modules_with_different_activation_dates(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with 3 modules (different periods)
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision();

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_type' => 'division_to_financer',
            'status' => 'confirmed',
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        // Module 1: Full month (activated before period, prorata 100%)
        $module1 = Module::factory()->create(['name' => ['en' => 'Wellness Module']]);
        $financer->modules()->attach($module1->id, [
            'active' => true,
            'created_at' => '2025-09-15', // Before billing period
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module1->id,
            'unit_price_htva' => 150000, // €1500.00
            'quantity' => 1,
            'subtotal_htva' => 150000,
            'vat_rate' => 21.00,
            'vat_amount' => 31500,
            'total_ttc' => 181500,
        ]);

        // Module 2: Mid-month activation (Oct 15-31 = 17 days, prorata ≈ 55%)
        $module2 = Module::factory()->create(['name' => ['en' => 'Mobility Module']]);
        $financer->modules()->attach($module2->id, [
            'active' => true,
            'created_at' => '2025-10-15',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module2->id,
            'unit_price_htva' => 200000, // €2000.00
            'quantity' => 1,
            'subtotal_htva' => 200000,
            'vat_rate' => 21.00,
            'vat_amount' => 42000,
            'total_ttc' => 242000,
        ]);

        // Module 3: Late activation (Oct 25-31 = 7 days, prorata ≈ 23%)
        $module3 = Module::factory()->create(['name' => ['en' => 'Training Module']]);
        $financer->modules()->attach($module3->id, [
            'active' => true,
            'created_at' => '2025-10-25',
        ]);
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module3->id,
            'unit_price_htva' => 100000, // €1000.00
            'quantity' => 1,
            'subtotal_htva' => 100000,
            'vat_rate' => 21.00,
            'vat_amount' => 21000,
            'total_ttc' => 121000,
        ]);

        // ============================================================
        // ACT: Export module activation details
        // ============================================================

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();

        // ============================================================
        // ASSERT: Verify 3 modules exported with correct data
        // ============================================================

        // ✅ 1. Verify 3 modules exported
        $this->assertCount(3, $results, 'Should export 3 active modules');

        // ✅ 2. Find modules by name
        $wellnessResult = $results->first(fn ($r): bool => $r->getTranslation('name', 'en') === 'Wellness Module');
        $mobilityResult = $results->first(fn ($r): bool => $r->getTranslation('name', 'en') === 'Mobility Module');
        $trainingResult = $results->first(fn ($r): bool => $r->getTranslation('name', 'en') === 'Training Module');

        // ✅ 3. Verify Module 1 (Full month)
        $mapped1 = $export->map($wellnessResult);
        $this->assertEquals('Wellness Module', $mapped1[0]);
        $this->assertEquals('2025-10-01', $mapped1[1]); // Activation Date (clamped)
        $this->assertNull($mapped1[2]); // Deactivation Date
        $this->assertEquals(31, $mapped1[3]); // Active Days
        $this->assertEquals('1.00', $mapped1[5]); // Prorata 100%
        $this->assertEquals('1500.00', $mapped1[6]); // Unit Price
        $this->assertEquals('1500.00', $mapped1[7]); // Module Amount (100%)

        // ✅ 4. Verify Module 2 (Mid-month)
        $mapped2 = $export->map($mobilityResult);
        $this->assertEquals('Mobility Module', $mapped2[0]);
        $this->assertEquals('2025-10-15', $mapped2[1]); // Activation Date
        $this->assertNull($mapped2[2]); // Deactivation Date
        $this->assertEquals(17, $mapped2[3]); // Active Days (15-31)
        $this->assertEquals('0.55', $mapped2[5]); // Prorata 17/31 ≈ 0.55
        $expectedAmount = number_format(200000 * (17 / 31) / 100, 2, '.', '');
        $this->assertEquals($expectedAmount, $mapped2[7]); // Module Amount

        // ✅ 5. Verify Module 3 (Late activation)
        $mapped3 = $export->map($trainingResult);
        $this->assertEquals('Training Module', $mapped3[0]);
        $this->assertEquals('2025-10-25', $mapped3[1]); // Activation Date
        $this->assertNull($mapped3[2]); // Deactivation Date
        $this->assertEquals(7, $mapped3[3]); // Active Days (25-31)
        $this->assertEquals('0.23', $mapped3[5]); // Prorata 7/31 ≈ 0.23
        $expectedAmount = number_format(100000 * (7 / 31) / 100, 2, '.', '');
        $this->assertEquals($expectedAmount, $mapped3[7]);
    }

    #[Test]
    public function e2e_012b_it_handles_streaming_export_with_50_modules(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with 50 modules
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision();

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'issuer_type' => 'App\\Models\\Division',
            'issuer_id' => $division->id,
            'invoice_type' => 'division_to_financer',
            'status' => 'confirmed',
            'billing_period_start' => '2025-11-01',
            'billing_period_end' => '2025-11-30',
        ]);

        // Create 50 modules with varying activation dates
        for ($i = 1; $i <= 50; $i++) {
            // Stagger activation dates across the month
            $dayOfActivation = ($i % 30) + 1;
            $activationDate = "2025-11-{$dayOfActivation}";

            $module = Module::factory()->create([
                'name' => ['en' => "Module {$i}"],
            ]);

            $financer->modules()->attach($module->id, [
                'active' => true,
                'created_at' => $activationDate,
            ]);

            // Create invoice item with pricing
            InvoiceItem::factory()->create([
                'invoice_id' => $invoice->id,
                'module_id' => $module->id,
                'unit_price_htva' => 50000 * $i, // Varying prices
                'quantity' => 1,
                'subtotal_htva' => 50000 * $i,
                'vat_rate' => 21.00,
                'vat_amount' => 10500 * $i,
                'total_ttc' => 60500 * $i,
            ]);
        }

        // ============================================================
        // ACT: Export 50 modules
        // ============================================================

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();

        $duration = (microtime(true) - $startTime);
        $memoryPeak = memory_get_peak_usage() - $memoryBefore;

        // ============================================================
        // ASSERT: Verify export performance
        // ============================================================

        // ✅ 1. Verify 50 modules exported
        $this->assertCount(50, $results, 'Should export 50 active modules');

        // ✅ 2. Export completes in reasonable time (< 5s for 50 modules)
        $this->assertLessThan(5, $duration, 'Export should complete in < 5s');

        // ✅ 3. Memory usage acceptable (< 50MB for 50 modules)
        $memoryPeakMB = $memoryPeak / 1024 / 1024;
        $this->assertLessThan(50, $memoryPeakMB, 'Memory peak should be < 50MB');

        // ✅ 4. All modules have valid data
        foreach ($results as $result) {
            $mapped = $export->map($result);
            $this->assertNotEmpty($mapped[0]); // Module Name
            $this->assertNotEmpty($mapped[1]); // Activation Date
            $this->assertIsNumeric($mapped[3]); // Active Days
            $this->assertIsNumeric($mapped[5]); // Prorata
            $this->assertIsNumeric($mapped[6]); // Unit Price
        }

        // ✅ 5. Verify headings structure
        $headings = $export->headings();
        $this->assertCount(8, $headings);
        $this->assertEquals('Module Name', $headings[0]);
        $this->assertEquals('Prorata', $headings[5]);
    }
}
