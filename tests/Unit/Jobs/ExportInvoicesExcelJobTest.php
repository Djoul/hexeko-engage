<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Actions\Invoicing\ExportInvoicesExcelAction;
use App\Jobs\Invoicing\ExportInvoicesExcelJob;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('jobs')]
class ExportInvoicesExcelJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('invoicing.export.queue', 'invoicing-export');
    }

    #[Test]
    public function it_dispatches_to_export_queue(): void
    {
        Queue::fake();

        ExportInvoicesExcelJob::dispatch(['status' => 'sent'], 'ops@example.com');

        Queue::assertPushed(ExportInvoicesExcelJob::class, function (ExportInvoicesExcelJob $job): bool {
            return $job->queue === 'invoicing-export'
                && $job->filters === ['status' => 'sent']
                && $job->email === 'ops@example.com';
        });
    }

    #[Test]
    public function it_invokes_export_action_with_filters(): void
    {
        $action = Mockery::mock(ExportInvoicesExcelAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with(['status' => 'paid'])
            ->andReturn(response()->streamDownload(static fn (): string => '', 'invoices.xlsx'));

        $job = new ExportInvoicesExcelJob(['status' => 'paid'], 'ops@example.com');
        $job->handle($action);
    }
}
