<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Actions\Invoicing\SendInvoiceEmailAction;
use App\Jobs\Invoicing\SendInvoiceEmailJob;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('jobs')]
class SendInvoiceEmailJobTest extends TestCase
{
    use DatabaseTransactions;

    private Invoice $invoice;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('invoicing.emails.queue', 'emails');
        $this->invoice = Invoice::factory()->create();
    }

    #[Test]
    public function it_dispatches_to_email_queue(): void
    {
        Queue::fake();

        SendInvoiceEmailJob::dispatch($this->invoice->id, 'billing@example.com');

        Queue::assertPushed(SendInvoiceEmailJob::class, function (SendInvoiceEmailJob $job): bool {
            return $job->queue === 'emails'
                && $job->invoiceId === $this->invoice->id
                && $job->recipientEmail === 'billing@example.com';
        });
    }

    #[Test]
    public function it_invokes_send_invoice_email_action(): void
    {
        Date::setTestNow('2025-05-03 10:30:00');

        $action = Mockery::mock(SendInvoiceEmailAction::class);
        $action->shouldReceive('execute')
            ->once()
            ->with($this->invoice->id, 'billing@example.com', ['finance@example.com'])
            ->andReturnNull();

        $job = new SendInvoiceEmailJob($this->invoice->id, 'billing@example.com', ['finance@example.com']);
        $job->handle($action);

        Date::setTestNow();
    }
}
