<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\SendInvoiceEmailAction;
use App\DTOs\Invoicing\CachedInvoicePdfDTO;
use App\Mail\InvoiceMail;
use App\Models\Invoice;
use App\Services\Invoicing\InvoicePdfCacheService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('email')]
class SendInvoiceEmailActionTest extends TestCase
{
    use DatabaseTransactions;

    private Invoice $invoice;

    private MockInterface $cacheService;

    private SendInvoiceEmailAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->invoice = Invoice::factory()->create();
        $this->cacheService = Mockery::mock(InvoicePdfCacheService::class);
        $this->app->instance(InvoicePdfCacheService::class, $this->cacheService);
        $this->action = $this->app->make(SendInvoiceEmailAction::class);
    }

    #[Test]
    public function it_sends_invoice_email_with_pdf_attachment(): void
    {
        Date::setTestNow('2025-05-02 12:00:00');

        $dto = new CachedInvoicePdfDTO(
            invoice: $this->invoice->fresh(),
            content: 'pdf-binary',
            path: 'invoices/pdf/'.$this->invoice->id.'.pdf',
            fromCache: true,
        );

        $this->cacheService->shouldReceive('get')
            ->once()
            ->with($this->invoice->id, false)
            ->andReturn($dto);

        Mail::fake();

        $this->action->execute($this->invoice->id, 'billing@example.com', ['finance@example.com']);

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            $mail->build();

            $this->assertSame('billing@example.com', $mail->to[0]['address']);
            $this->assertSame('finance@example.com', $mail->cc[0]['address']);
            $this->assertNotEmpty($mail->rawAttachments);
            $attachment = $mail->rawAttachments[0];
            $this->assertSame('application/pdf', $attachment['options']['mime'] ?? null);

            return true;
        });

        $this->assertNotNull($this->invoice->fresh()->sent_at);

        Date::setTestNow();
    }

    #[Test]
    public function it_handles_emails_without_cc_addresses(): void
    {
        $dto = new CachedInvoicePdfDTO(
            invoice: $this->invoice->fresh(),
            content: 'pdf-binary',
            path: 'invoices/pdf/'.$this->invoice->id.'.pdf',
            fromCache: false,
        );

        $this->cacheService->shouldReceive('get')
            ->once()
            ->with($this->invoice->id, false)
            ->andReturn($dto);

        Mail::fake();

        $this->action->execute($this->invoice->id, 'billing@example.com');

        Mail::assertQueued(InvoiceMail::class, function (InvoiceMail $mail): bool {
            $mail->build();

            $this->assertSame('billing@example.com', $mail->to[0]['address']);
            $this->assertSame([], $mail->cc);

            return true;
        });
    }
}
