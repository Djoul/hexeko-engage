<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Models\Invoicing\Invoice;
use App\Models\Module;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;
use Tests\Helpers\Facades\ModelFactory;

/**
 * E2E-002: Multi-Level Prorata Calculation
 *
 * Validates simultaneous prorata calculations:
 * 1. Contract prorata (contract start date)
 * 2. Beneficiary prorata (beneficiary activation date)
 * 3. Module prorata (module activation date)
 *
 * Critical Test Scenario:
 * - Contract starts 20/10 (prorata contract)
 * - Beneficiary added 10/10 (prorata beneficiary)
 * - Module activated 15/10 (prorata module)
 *
 * Expected Calculations:
 * ✅ Contract prorata: 12/31 = 0.3871
 * ✅ Beneficiary prorata: 22/31 = 0.7097
 * ✅ Module prorata: 17/31 = 0.5484
 * ✅ Core Package = €3000 × 0.3871 × 0.7097
 * ✅ Module = €1000 × 0.5484 × 0.7097
 * ✅ Amounts precise to eurocent
 */
#[Group('e2e')]
#[Group('e2e-critical')]
#[Group('invoicing')]
#[Group('prorata-calculation')]
class ProrataCalculationTest extends E2ETestCase
{
    #[Test]
    public function e2e_002_it_calculates_three_level_prorata_correctly(): void
    {
        // ============================================================
        // ARRANGE: Setup complex prorata scenario for October 2025
        // ============================================================

        // Set current date to October 2025 (31 days)
        Carbon::setTestNow(Carbon::create(2025, 10, 25, 12, 0, 0));

        ['division' => $division, 'financer' => $financer] = $this->createTestFinancerWithDivision([
            'country' => 'BE',
            'vat_rate' => 21.00,
            'core_package_price' => 300000, // €3000.00
        ], [
            'contract_start_date' => Carbon::create(2025, 10, 20), // Contract starts 20/10
            'core_package_price' => 300000, // €3000.00
        ]);

        // Create module activated on 15/10
        $module = Module::factory()->create([
            'name' => 'Wellness Module',
            'is_core' => false,
        ]);

        // Attach module with activation date
        $financer->modules()->attach($module->id, [
            'active' => true,
            'price' => 100000, // €1000.00
            'created_at' => Carbon::create(2025, 10, 15), // Module activated 15/10
        ]);

        // Create beneficiary added on 10/10
        ModelFactory::createUser([
            'email' => 'prorata.user@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'from' => Carbon::create(2025, 10, 10), // Beneficiary added 10/10
                ],
            ],
        ], false);

        $monthYear = '2025-10';

        // ============================================================
        // ACT: Generate invoice with complex prorata
        // ============================================================

        $invoice = $this->generateInvoice($financer->id, $monthYear);

        // ============================================================
        // ASSERT: Validate multi-level prorata calculations
        // ============================================================

        $this->assertNotNull($invoice);

        // ✅ 1. Verify contract prorata: 12 days remaining / 31 total = 0.3871
        $expectedContractProrata = round(12 / 31, 4); // Days from 20/10 to 31/10 = 12 days
        $this->assertEquals(
            $expectedContractProrata,
            $invoice->contract_prorata_ratio,
            'Contract prorata should be 12/31 = 0.3871'
        );

        // ✅ 2. Verify invoice items exist
        $coreItem = $invoice->invoice_items()->where('description', 'LIKE', '%Core Package%')->first();
        $moduleItem = $invoice->invoice_items()->where('module_id', $module->id)->first();

        $this->assertNotNull($coreItem, 'Core package item should exist');
        $this->assertNotNull($moduleItem, 'Module item should exist');

        // ✅ 3. Verify Core Package calculation
        // Core Package = €3000 × contract_prorata × beneficiary_prorata
        // Beneficiary prorata = 22/31 (from 10/10 to 31/10)
        $beneficiaryProrata = round(22 / 31, 4);
        $expectedCoreAmount = (int) round(300000 * $expectedContractProrata * $beneficiaryProrata);

        $this->assertEquals(
            $expectedCoreAmount,
            $coreItem->total_amount,
            'Core package amount should match multi-level prorata calculation'
        );

        // ✅ 4. Verify Module calculation
        // Module = €1000 × module_prorata × beneficiary_prorata
        // Module prorata = 17/31 (from 15/10 to 31/10)
        $moduleProrata = round(17 / 31, 4);
        $expectedModuleAmount = (int) round(100000 * $moduleProrata * $beneficiaryProrata);

        $this->assertEquals(
            $expectedModuleAmount,
            $moduleItem->total_amount,
            'Module amount should match multi-level prorata calculation'
        );

        // ✅ 5. Verify total invoice amount
        $expectedSubtotal = $expectedCoreAmount + $expectedModuleAmount;
        $expectedVat = (int) round($expectedSubtotal * 0.21);
        $expectedTotal = $expectedSubtotal + $expectedVat;

        $this->assertEquals($expectedSubtotal, $invoice->subtotal_amount);
        $this->assertEquals($expectedVat, $invoice->vat_amount);
        $this->assertEquals($expectedTotal, $invoice->total_amount);

        // ✅ 6. Verify precision (amounts in cents - no rounding errors)
        $this->assertIsInt($invoice->total_amount);
        $this->assertIsInt($coreItem->total_amount);
        $this->assertIsInt($moduleItem->total_amount);

        // ✅ 7. Verify prorata metadata stored
        $this->assertArrayHasKey('contract_start_date', $invoice->metadata ?? []);
        $this->assertArrayHasKey('prorata_details', $invoice->metadata ?? []);

        Carbon::setTestNow(); // Reset test time
    }

    #[Test]
    public function e2e_003a_it_calculates_prorata_for_february_non_leap_year(): void
    {
        // ============================================================
        // E2E-003A: February Non-Leap Year (28 days)
        // ============================================================

        Carbon::setTestNow(Carbon::create(2025, 2, 20, 12, 0, 0));

        ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
            'contract_start_date' => Carbon::create(2025, 2, 15), // Mid-February
            'core_package_price' => 280000, // €2800.00
        ]);

        // Add beneficiary for full month
        ModelFactory::createUser([
            'email' => 'feb.user@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => Carbon::create(2025, 2, 1)],
            ],
        ], false);

        $invoice = $this->generateInvoice($financer->id, '2025-02');

        // Contract starts 15/02, days remaining = 14 (15-28)
        $expectedProrata = round(14 / 28, 4); // 0.5000
        $this->assertEquals($expectedProrata, $invoice->contract_prorata_ratio);

        // Verify calculation: €2800 × 0.5 × 1.0 (full month beneficiary)
        $expectedAmount = (int) round(280000 * 0.5);
        $coreItem = $invoice->invoice_items()->first();
        $this->assertEquals($expectedAmount, $coreItem->total_amount);

        Carbon::setTestNow();
    }

    #[Test]
    public function e2e_003b_it_calculates_prorata_for_february_leap_year(): void
    {
        // ============================================================
        // E2E-003B: February Leap Year (29 days)
        // ============================================================

        Carbon::setTestNow(Carbon::create(2024, 2, 20, 12, 0, 0));

        ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
            'contract_start_date' => Carbon::create(2024, 2, 15), // Mid-February leap year
            'core_package_price' => 290000, // €2900.00
        ]);

        ModelFactory::createUser([
            'email' => 'feb.leap.user@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => Carbon::create(2024, 2, 1)],
            ],
        ], false);

        $invoice = $this->generateInvoice($financer->id, '2024-02');

        // Contract starts 15/02, days remaining = 15 (15-29)
        $expectedProrata = round(15 / 29, 4); // 0.5172
        $this->assertEquals($expectedProrata, $invoice->contract_prorata_ratio);

        // Verify different prorata than non-leap year
        $this->assertNotEquals(0.5000, $expectedProrata, 'Leap year prorata should differ from non-leap year');

        Carbon::setTestNow();
    }

    #[Test]
    public function e2e_003c_it_handles_month_length_differences(): void
    {
        // ============================================================
        // E2E-003C: Month 31 vs 30 days comparison
        // ============================================================

        $testCases = [
            ['month' => '2025-01', 'days' => 31, 'start_day' => 15], // January (31 days)
            ['month' => '2025-04', 'days' => 30, 'start_day' => 15], // April (30 days)
        ];

        $proratas = [];

        foreach ($testCases as $case) {
            $monthDate = Carbon::createFromFormat('Y-m', $case['month']);
            Carbon::setTestNow($monthDate->copy()->addDays(20));

            ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
                'contract_start_date' => $monthDate->copy()->day($case['start_day']),
                'core_package_price' => 300000,
                'name' => "Financer {$case['month']}",
            ]);

            ModelFactory::createUser([
                'email' => "user.{$case['month']}@test.com",
                'financers' => [
                    ['financer' => $financer, 'active' => true, 'from' => $monthDate->copy()->startOfMonth()],
                ],
            ], false);

            $invoice = $this->generateInvoice($financer->id, $case['month']);

            $daysRemaining = $case['days'] - $case['start_day'] + 1;
            $expectedProrata = round($daysRemaining / $case['days'], 4);

            $this->assertEquals($expectedProrata, $invoice->contract_prorata_ratio);
            $proratas[$case['month']] = $expectedProrata;
        }

        // Verify that proratas are different for different month lengths
        $this->assertNotEquals(
            $proratas['2025-01'],
            $proratas['2025-04'],
            'Prorata should differ between 31-day and 30-day months'
        );

        Carbon::setTestNow();
    }

    #[Test]
    public function e2e_003d_it_handles_end_of_month_contract_start(): void
    {
        // ============================================================
        // E2E-003D: Contract starting on last day of month
        // ============================================================

        Carbon::setTestNow(Carbon::create(2025, 1, 31, 12, 0, 0));

        ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
            'contract_start_date' => Carbon::create(2025, 1, 31), // Last day of January
            'core_package_price' => 310000,
        ]);

        ModelFactory::createUser([
            'email' => 'eom.user@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => Carbon::create(2025, 1, 1)],
            ],
        ], false);

        $invoice = $this->generateInvoice($financer->id, '2025-01');

        // Only 1 day remaining (31/01)
        $expectedProrata = round(1 / 31, 4); // 0.0323
        $this->assertEquals($expectedProrata, $invoice->contract_prorata_ratio);

        // Verify invoice amount reflects single day
        $coreItem = $invoice->invoice_items()->first();
        $expectedAmount = (int) round(310000 * $expectedProrata);
        $this->assertEquals($expectedAmount, $coreItem->total_amount);

        Carbon::setTestNow();
    }

    #[Test]
    public function e2e_002b_it_validates_prorata_precision_to_eurocent(): void
    {
        // ============================================================
        // Validate that all prorata calculations are precise to cent
        // ============================================================

        Carbon::setTestNow(Carbon::create(2025, 10, 25, 12, 0, 0));

        ['financer' => $financer] = $this->createTestFinancerWithDivision([], [
            'contract_start_date' => Carbon::create(2025, 10, 17),
            'core_package_price' => 333333, // Odd number to test rounding
        ]);

        $module = Module::factory()->create(['is_core' => false]);
        $financer->modules()->attach($module->id, [
            'active' => true,
            'price' => 77777, // Another odd number
            'created_at' => Carbon::create(2025, 10, 12),
        ]);

        ModelFactory::createUser([
            'email' => 'precision.user@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => Carbon::create(2025, 10, 8)],
            ],
        ], false);

        $invoice = $this->generateInvoice($financer->id, '2025-10');

        // ✅ All amounts must be integers (cents)
        $this->assertIsInt($invoice->subtotal_amount);
        $this->assertIsInt($invoice->vat_amount);
        $this->assertIsInt($invoice->total_amount);

        foreach ($invoice->invoice_items as $item) {
            $this->assertIsInt($item->unit_price);
            $this->assertIsInt($item->total_amount);
        }

        // ✅ Total must equal sum of items + VAT
        $itemsSum = $invoice->invoice_items->sum('total_amount');
        $this->assertEquals($itemsSum, $invoice->subtotal_amount);

        $expectedTotal = $invoice->subtotal_amount + $invoice->vat_amount;
        $this->assertEquals($expectedTotal, $invoice->total_amount);

        // ✅ No fractional cents
        $this->assertEquals(0, $invoice->total_amount % 1);

        Carbon::setTestNow();
    }
}
