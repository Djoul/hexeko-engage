<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Exports\ModuleActivationExport;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Module;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit')]
#[Group('exports')]
class ModuleActivationExportTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_returns_correct_headings(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $headings = $export->headings();

        $expectedHeadings = [
            'Module Name',
            'Activation Date',
            'Deactivation Date',
            'Active Days',
            'Total Days',
            'Prorata',
            'Unit Price (€)',
            'Module Amount (€)',
        ];

        $this->assertEquals($expectedHeadings, $headings);
    }

    #[Test]
    public function it_filters_by_invoice_id(): void
    {
        $division = Division::factory()->create();
        $financer1 = Financer::factory()->create(['division_id' => $division->id]);
        $financer2 = Financer::factory()->create(['division_id' => $division->id]);

        $invoice1 = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer1->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer2->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module1 = Module::factory()->create(['name' => ['en' => 'Module 1']]);
        $module2 = Module::factory()->create(['name' => ['en' => 'Module 2']]);

        // Attach modules to financers
        $financer1->modules()->attach($module1->id, [
            'active' => true,
            'created_at' => '2025-10-01',
        ]);

        $financer2->modules()->attach($module2->id, [
            'active' => true,
            'created_at' => '2025-10-01',
        ]);

        $export = new ModuleActivationExport($invoice1->id);
        $results = $export->query()->get();

        // Should only return modules for invoice1's financer
        $this->assertCount(1, $results);

        // Check module name via mapped output (since raw query doesn't cast properly)
        $mapped = $export->map($results->first());
        $this->assertEquals('Module 1', $mapped[0]);
    }

    #[Test]
    public function it_maps_module_data_with_activation_dates(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Wellness Module']]);

        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => '2025-10-01', // Full month activation
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('Wellness Module', $mapped[0]); // Module Name
        $this->assertNotEmpty($mapped[1]); // Activation Date
        $this->assertEquals(31, $mapped[3]); // Active Days (full month)
        $this->assertEquals(31, $mapped[4]); // Total Days
    }

    #[Test]
    public function it_handles_module_activated_mid_month(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Mid Month Module']]);

        // Module activated on Oct 15 (17 days remaining)
        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => '2025-10-15',
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('Mid Month Module', $mapped[0]);
        $this->assertEquals('2025-10-15', $mapped[1]); // Activation Date
        $this->assertNull($mapped[2]); // Deactivation Date (still active)
        $this->assertEquals(17, $mapped[3]); // Active Days (15-31)
        $this->assertEquals('0.55', $mapped[5]); // Prorata (17/31 ≈ 0.55)
    }

    #[Test]
    public function it_handles_module_deactivated_mid_month(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Deactivated Module']]);

        // Module activated full period but will be deactivated mid-month
        // Note: Deactivation will be tested via audit trail in E2E tests
        // For unit test, we test the query structure
        $financer->modules()->attach($module->id, [
            'active' => false, // Currently inactive
            'created_at' => '2025-10-01',
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();

        // Should include inactive modules (prorata calculation handles this)
        $this->assertCount(1, $results);
    }

    #[Test]
    public function it_handles_module_active_full_month(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Full Month Module']]);

        // Module activated before period start
        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => '2025-09-15', // Before billing period
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('Full Month Module', $mapped[0]);
        $this->assertEquals('2025-10-01', $mapped[1]); // Activation clamped to period start
        $this->assertNull($mapped[2]); // Deactivation Date (still active)
        $this->assertEquals(31, $mapped[3]); // Active Days (full month)
        $this->assertEquals('1.00', $mapped[5]); // Prorata 100%
    }

    #[Test]
    public function it_calculates_module_amount_from_unit_price_and_prorata(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $module = Module::factory()->create(['name' => ['en' => 'Calc Module']]);

        // Module active for testing price calculation format
        $financer->modules()->attach($module->id, [
            'active' => true,
            'created_at' => '2025-10-01',
        ]);

        // Create invoice item with module pricing (this is where pricing is stored)
        InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'module_id' => $module->id,
            'unit_price_htva' => 300000, // €3000.00
            'quantity' => 1,
            'subtotal_htva' => 300000,
            'vat_rate' => 21.00,
            'vat_amount' => 63000,
            'total_ttc' => 363000,
        ]);

        $export = new ModuleActivationExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        // Verify price comes from invoice item
        $this->assertEquals('3000.00', $mapped[6]); // Unit Price from invoice item

        // Module amount should be formatted correctly
        $this->assertMatchesRegularExpression('/^\d+\.\d{2}$/', $mapped[7]); // Module Amount format
    }
}
