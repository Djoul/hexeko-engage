<?php

declare(strict_types=1);

namespace Tests\E2E\Invoicing;

use App\Exports\UserBillingDetailsExport;
use App\Models\Invoice;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\E2E\E2ETestCase;
use Tests\Helpers\Facades\ModelFactory;

/**
 * E2E-011: User Billing Details Export
 *
 * Validates detailed user billing export with:
 * ✅ Multiple users with different prorata ratios
 * ✅ Correct period calculations (from/to dates)
 * ✅ Accurate user amount calculations
 * ✅ Temporal overlap logic
 * ✅ Streaming export with large datasets
 */
#[Group('e2e')]
#[Group('e2e-critical')]
#[Group('invoicing')]
#[Group('exports')]
class UserBillingExportTest extends E2ETestCase
{
    #[Test]
    public function e2e_011_it_exports_users_with_different_prorata_ratios(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with 3 users (different periods)
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
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        // User 1: Full month (prorata 100%)
        ModelFactory::createUser([
            'first_name' => 'Alice',
            'last_name' => 'Full',
            'email' => 'alice@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'from' => '2025-10-01',
                    'to' => null, // Still active
                ],
            ],
        ]);

        // User 2: Half month (Oct 1-15 = 15 days, prorata ≈ 48%)
        ModelFactory::createUser([
            'first_name' => 'Bob',
            'last_name' => 'Half',
            'email' => 'bob@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'from' => '2025-10-01',
                    'to' => '2025-10-15',
                ],
            ],
        ]);

        // User 3: Late joiner (Oct 20-31 = 12 days, prorata ≈ 39%)
        ModelFactory::createUser([
            'first_name' => 'Charlie',
            'last_name' => 'Late',
            'email' => 'charlie@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'from' => '2025-10-20',
                    'to' => null,
                ],
            ],
        ]);

        // ============================================================
        // ACT: Export user billing details
        // ============================================================

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();

        // ============================================================
        // ASSERT: Verify 3 users exported with correct data
        // ============================================================

        // ✅ 1. Verify 3 users exported
        $this->assertCount(3, $results, 'Should export 3 active users');

        // ✅ 2. Verify user order (by email)
        $emails = $results->pluck('email')->toArray();
        $this->assertEquals(['alice@test.com', 'bob@test.com', 'charlie@test.com'], $emails);

        // ✅ 3. Verify User 1 (Full month)
        $mapped1 = $export->map($results->where('email', 'alice@test.com')->first());
        $this->assertEquals('Alice Full', $mapped1[0]);
        $this->assertEquals('2025-10-01', $mapped1[2]); // Period From
        $this->assertEquals('2025-10-31', $mapped1[3]); // Period To
        $this->assertEquals(31, $mapped1[4]); // Active Days
        $this->assertEquals('1.00', $mapped1[6]); // Prorata 100%
        $this->assertEquals('3000.00', $mapped1[7]); // Unit Price
        $this->assertEquals('3000.00', $mapped1[8]); // User Amount (100%)

        // ✅ 4. Verify User 2 (Half month)
        $mapped2 = $export->map($results->where('email', 'bob@test.com')->first());
        $this->assertEquals('Bob Half', $mapped2[0]);
        $this->assertEquals('2025-10-01', $mapped2[2]);
        $this->assertEquals('2025-10-15', $mapped2[3]);
        $this->assertEquals(15, $mapped2[4]); // Active Days
        $this->assertEquals('0.48', $mapped2[6]); // Prorata 15/31 ≈ 0.48
        $expectedAmount = number_format(300000 * (15 / 31) / 100, 2, '.', '');
        $this->assertEquals($expectedAmount, $mapped2[8]); // User Amount

        // ✅ 5. Verify User 3 (Late joiner)
        $mapped3 = $export->map($results->where('email', 'charlie@test.com')->first());
        $this->assertEquals('Charlie Late', $mapped3[0]);
        $this->assertEquals('2025-10-20', $mapped3[2]);
        $this->assertEquals('2025-10-31', $mapped3[3]);
        $this->assertEquals(12, $mapped3[4]); // Active Days (20-31)
        $this->assertEquals('0.39', $mapped3[6]); // Prorata 12/31 ≈ 0.39
        $expectedAmount = number_format(300000 * (12 / 31) / 100, 2, '.', '');
        $this->assertEquals($expectedAmount, $mapped3[8]);
    }

    #[Test]
    public function e2e_011b_it_handles_streaming_export_with_100_users(): void
    {
        // ============================================================
        // ARRANGE: Create invoice with 100 users
        // ============================================================

        ['financer' => $financer, 'division' => $division] = $this->createTestFinancerWithDivision([], [
            'core_package_price' => 200000, // €2000.00
        ]);

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

        // Create 100 users with varying join dates
        for ($i = 1; $i <= 100; $i++) {
            // Stagger join dates across the month
            $dayOfJoin = ($i % 30) + 1;
            $fromDate = "2025-11-{$dayOfJoin}";

            ModelFactory::createUser([
                'first_name' => "User{$i}",
                'last_name' => 'Test',
                'email' => "user{$i}@test.com",
                'financers' => [
                    [
                        'financer' => $financer,
                        'active' => true,
                        'from' => $fromDate,
                        'to' => null,
                    ],
                ],
            ]);
        }

        // ============================================================
        // ACT: Export 100 users
        // ============================================================

        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();

        $duration = (microtime(true) - $startTime);
        $memoryPeak = memory_get_peak_usage() - $memoryBefore;

        // ============================================================
        // ASSERT: Verify export performance
        // ============================================================

        // ✅ 1. Verify 100 users exported
        $this->assertCount(100, $results, 'Should export 100 active users');

        // ✅ 2. Export completes in reasonable time (< 5s for 100 users)
        $this->assertLessThan(5, $duration, 'Export should complete in < 5s');

        // ✅ 3. Memory usage acceptable (< 50MB for 100 users)
        $memoryPeakMB = $memoryPeak / 1024 / 1024;
        $this->assertLessThan(50, $memoryPeakMB, 'Memory peak should be < 50MB');

        // ✅ 4. All users have valid data
        foreach ($results as $result) {
            $mapped = $export->map($result);
            $this->assertNotEmpty($mapped[0]); // User Name
            $this->assertNotEmpty($mapped[1]); // Email
            $this->assertNotEmpty($mapped[2]); // Period From
            $this->assertIsNumeric($mapped[4]); // Active Days
            $this->assertIsNumeric($mapped[6]); // Prorata
        }

        // ✅ 5. Verify headings structure
        $headings = $export->headings();
        $this->assertCount(9, $headings);
        $this->assertEquals('User Name', $headings[0]);
        $this->assertEquals('Prorata', $headings[6]);
    }
}
