<?php

declare(strict_types=1);

namespace Tests\Unit\Exports;

use App\Exports\UserBillingDetailsExport;
use App\Models\Division;
use App\Models\Financer;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('unit')]
#[Group('exports')]
class UserBillingDetailsExportTest extends TestCase
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

        $export = new UserBillingDetailsExport($invoice->id);
        $headings = $export->headings();

        $expectedHeadings = [
            'User Name',
            'Email',
            'Period From',
            'Period To',
            'Active Days',
            'Total Days',
            'Prorata',
            'Unit Price (€)',
            'User Amount (€)',
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

        // Create users for each financer
        $user1 = User::factory()->create(['email' => 'user1@test.com']);
        $user2 = User::factory()->create(['email' => 'user2@test.com']);

        $financer1->users()->attach($user1->id, [
            'active' => true,
            'from' => '2025-10-01',
            'to' => null,
        ]);

        $financer2->users()->attach($user2->id, [
            'active' => true,
            'from' => '2025-10-01',
            'to' => null,
        ]);

        $export = new UserBillingDetailsExport($invoice1->id);
        $results = $export->query()->get();

        // Should only return users for invoice1's financer
        $this->assertCount(1, $results);
        $this->assertEquals('user1@test.com', $results->first()->email);
    }

    #[Test]
    public function it_maps_user_data_correctly_with_prorata(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $user = User::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@test.com',
        ]);

        $financer->users()->attach($user->id, [
            'active' => true,
            'from' => '2025-10-01', // Full month
            'to' => null,
        ]);

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('John Doe', $mapped[0]); // User Name
        $this->assertEquals('john.doe@test.com', $mapped[1]); // Email
        $this->assertEquals('2025-10-01', $mapped[2]); // Period From
        $this->assertEquals('2025-10-31', $mapped[3]); // Period To (null becomes period end)
        $this->assertEquals(31, $mapped[4]); // Active Days
        $this->assertEquals(31, $mapped[5]); // Total Days
        $this->assertEquals('1.00', $mapped[6]); // Prorata (full month = 100%)
    }

    #[Test]
    public function it_handles_users_with_partial_month_from_to_dates(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $user = User::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@test.com',
        ]);

        // User active from Oct 10 to Oct 20 (11 days)
        $financer->users()->attach($user->id, [
            'active' => true,
            'from' => '2025-10-10',
            'to' => '2025-10-20',
        ]);

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('2025-10-10', $mapped[2]); // Period From
        $this->assertEquals('2025-10-20', $mapped[3]); // Period To
        $this->assertEquals(11, $mapped[4]); // Active Days (10-20 inclusive)
        $this->assertEquals(31, $mapped[5]); // Total Days
        $this->assertEquals('0.35', $mapped[6]); // Prorata (11/31 = 0.3548 ≈ 0.35)
    }

    #[Test]
    public function it_handles_users_with_full_month_to_null(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $user = User::factory()->create(['email' => 'active@test.com']);

        $financer->users()->attach($user->id, [
            'active' => true,
            'from' => '2025-09-15', // Before period start
            'to' => null, // Still active
        ]);

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        $this->assertEquals('2025-10-01', $mapped[2]); // Period From (clamped to billing start)
        $this->assertEquals('2025-10-31', $mapped[3]); // Period To (billing end)
        $this->assertEquals(31, $mapped[4]); // Active Days (full month)
        $this->assertEquals('1.00', $mapped[6]); // Prorata (100%)
    }

    #[Test]
    public function it_calculates_user_amount_from_unit_price_and_prorata(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create([
            'division_id' => $division->id,
            'core_package_price' => 300000, // €3000.00
        ]);

        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $user = User::factory()->create(['email' => 'calc@test.com']);

        // User active half the month (Oct 1-15 = 15 days)
        $financer->users()->attach($user->id, [
            'active' => true,
            'from' => '2025-10-01',
            'to' => '2025-10-15',
        ]);

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();
        $mapped = $export->map($results->first());

        // Unit price per beneficiary
        $this->assertEquals('3000.00', $mapped[7]); // Unit Price (€3000.00)

        // User amount = €3000 × (15/31) = €3000 × 0.48 ≈ €1451.61
        $expectedAmount = 300000 * (15 / 31) / 100;
        $this->assertEquals(number_format($expectedAmount, 2, '.', ''), $mapped[8]);
    }

    #[Test]
    public function it_excludes_inactive_users(): void
    {
        $division = Division::factory()->create();
        $financer = Financer::factory()->create(['division_id' => $division->id]);
        $invoice = Invoice::factory()->create([
            'recipient_type' => 'App\\Models\\Financer',
            'recipient_id' => $financer->id,
            'billing_period_start' => '2025-10-01',
            'billing_period_end' => '2025-10-31',
        ]);

        $activeUser = User::factory()->create(['email' => 'active@test.com']);
        $inactiveUser = User::factory()->create(['email' => 'inactive@test.com']);

        $financer->users()->attach($activeUser->id, [
            'active' => true,
            'from' => '2025-10-01',
            'to' => null,
        ]);

        $financer->users()->attach($inactiveUser->id, [
            'active' => false, // Inactive user
            'from' => '2025-10-01',
            'to' => null,
        ]);

        $export = new UserBillingDetailsExport($invoice->id);
        $results = $export->query()->get();

        // Should only return active user
        $this->assertCount(1, $results);
        $this->assertEquals('active@test.com', $results->first()->email);
    }
}
