<?php

declare(strict_types=1);

namespace Tests\Unit\Aggregates;

use App\Aggregates\DivisionBalanceAggregate;
use App\Models\DivisionBalance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('event-sourcing')]
class DivisionBalanceAggregateTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_tracks_balance_when_invoice_is_generated(): void
    {
        $division = ModelFactory::createDivision();
        $divisionId = $division->id;
        $invoiceId = Str::uuid()->toString();
        $amount = 125_00;
        $generatedAt = Carbon::parse('2025-02-05 10:00:00');

        DivisionBalanceAggregate::retrieve($divisionId)
            ->invoiceGenerated($divisionId, $invoiceId, $amount, $generatedAt)
            ->persist();

        $this->assertDatabaseHas('division_balances', [
            'division_id' => $divisionId,
            'balance' => $amount,
        ]);

        $balance = DivisionBalance::where('division_id', $divisionId)->first();
        $this->assertNotNull($balance);
        $this->assertTrue($generatedAt->equalTo($balance->last_invoice_at));
        $this->assertNull($balance->last_payment_at);
        $this->assertSame($amount, DivisionBalanceAggregate::retrieve($divisionId)->getBalance());
    }

    #[Test]
    public function it_decreases_balance_when_invoice_is_paid(): void
    {
        $division = ModelFactory::createDivision();
        $divisionId = $division->id;
        $invoiceId = Str::uuid()->toString();
        $generatedAt = Carbon::parse('2025-03-01 09:30:00');
        $paidAt = Carbon::parse('2025-03-15 14:20:00');

        DivisionBalanceAggregate::retrieve($divisionId)
            ->invoiceGenerated($divisionId, $invoiceId, 50_000, $generatedAt)
            ->persist();

        DivisionBalanceAggregate::retrieve($divisionId)
            ->invoicePaid($divisionId, $invoiceId, 30_000, $paidAt)
            ->persist();

        $this->assertDatabaseHas('division_balances', [
            'division_id' => $divisionId,
            'balance' => 20_000,
        ]);

        $balance = DivisionBalance::where('division_id', $divisionId)->firstOrFail();
        $this->assertTrue($paidAt->equalTo($balance->last_payment_at));
        $this->assertSame(20_000, DivisionBalanceAggregate::retrieve($divisionId)->getBalance());
    }

    #[Test]
    public function it_applies_credit_notes_to_balance(): void
    {
        $division = ModelFactory::createDivision();
        $divisionId = $division->id;
        $invoiceId = Str::uuid()->toString();
        $generatedAt = Carbon::parse('2025-04-01 08:00:00');
        $creditAt = Carbon::parse('2025-04-10 10:15:00');

        DivisionBalanceAggregate::retrieve($divisionId)
            ->invoiceGenerated($divisionId, $invoiceId, 40_000, $generatedAt)
            ->persist();

        DivisionBalanceAggregate::retrieve($divisionId)
            ->creditApplied($divisionId, $invoiceId, 5_000, 'Credit note #CN-42', $creditAt)
            ->persist();

        $this->assertDatabaseHas('division_balances', [
            'division_id' => $divisionId,
            'balance' => 35_000,
        ]);

        $balance = DivisionBalance::where('division_id', $divisionId)->firstOrFail();
        $this->assertTrue($creditAt->equalTo($balance->last_credit_at));
        $this->assertSame(35_000, DivisionBalanceAggregate::retrieve($divisionId)->getBalance());
    }
}
