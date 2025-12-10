<?php

declare(strict_types=1);

namespace Tests\Unit\Console\Commands;

use App\Actions\Invoicing\GenerateMonthlyInvoicesAction;
use App\DTOs\Invoicing\InvoiceBatchDTO;
use Illuminate\Support\Facades\Date;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('commands')]
class GenerateMonthlyInvoicesCommandTest extends TestCase
{
    #[Test]
    public function it_uses_current_month_when_option_not_provided(): void
    {
        Date::setTestNow('2025-05-12 10:00:00');

        $action = Mockery::mock(GenerateMonthlyInvoicesAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with('2025-05', null, null, false)
            ->andReturn(new InvoiceBatchDTO('batch-123', '2025-05', 5, 'completed'));

        $this->app->instance(GenerateMonthlyInvoicesAction::class, $action);

        $this->artisan('financer:generate-invoices')
            ->expectsOutputToContain('Batch ID: batch-123')
            ->expectsOutputToContain('Status: completed')
            ->expectsOutputToContain('Total invoices: 5')
            ->assertExitCode(0);

        Date::setTestNow();
    }

    #[Test]
    public function it_supports_dry_run_option(): void
    {
        Date::setTestNow('2025-06-01 00:00:00');

        $action = Mockery::mock(GenerateMonthlyInvoicesAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with('2025-06', 'division-id', null, true)
            ->andReturn(new InvoiceBatchDTO('batch-dry', '2025-06', 0, 'dry_run'));

        $this->app->instance(GenerateMonthlyInvoicesAction::class, $action);

        $this->artisan('financer:generate-invoices --month=2025-06 --division=division-id --dry-run')
            ->expectsOutputToContain('Status: dry_run')
            ->assertExitCode(0);

        Date::setTestNow();
    }
}
