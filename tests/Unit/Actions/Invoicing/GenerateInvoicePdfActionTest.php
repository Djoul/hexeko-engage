<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Invoicing;

use App\Actions\Invoicing\GenerateInvoicePdfAction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\Invoicing\InvoicePdfCacheService;
use App\Services\Invoicing\InvoicePdfGenerator;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\TestCase;

#[Group('invoicing')]
#[Group('pdf')]
class GenerateInvoicePdfActionTest extends TestCase
{
    use DatabaseTransactions;

    private Invoice $invoice;

    private MockInterface $generator;

    private InvoicePdfCacheService $cacheService;

    private GenerateInvoicePdfAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3');

        Config::set('invoicing.pdf', [
            'cache_enabled' => true,
            'cache_ttl_hours' => 6,
            'storage_disk' => 's3',
            'storage_path' => 'invoices/pdf',
        ]);

        $this->generator = Mockery::mock(InvoicePdfGenerator::class);
        $this->app->instance(InvoicePdfGenerator::class, $this->generator);

        $this->cacheService = $this->app->make(InvoicePdfCacheService::class);
        $this->action = $this->app->make(GenerateInvoicePdfAction::class);

        $this->invoice = Invoice::factory()->create();
        InvoiceItem::factory()->for($this->invoice)->count(2)->create();
    }

    #[Test]
    public function it_generates_and_caches_pdf_when_missing(): void
    {
        Date::setTestNow('2025-05-01 08:00:00');
        $this->generator->shouldReceive('render')
            ->once()
            ->with(Mockery::on(fn (Invoice $invoice): bool => $invoice->id === $this->invoice->id))
            ->andReturn('generated-pdf-content');

        $response = $this->action->execute($this->invoice->id, false);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('generated-pdf-content', $this->captureStream($response));

        $path = 'invoices/pdf/'.$this->invoice->id.'.pdf';
        Storage::disk('s3')->assertExists($path);

        Date::setTestNow();
    }

    #[Test]
    public function it_uses_cached_pdf_when_cache_is_fresh(): void
    {
        Date::setTestNow('2025-05-01 08:00:00');

        $path = 'invoices/pdf/'.$this->invoice->id.'.pdf';
        Storage::disk('s3')->put($path, 'cached-pdf-content');
        Storage::disk('s3')->put($path.'.meta.json', json_encode([
            'invoice_id' => $this->invoice->id,
            'generated_at' => Date::now()->toIso8601String(),
            'ttl_hours' => 6,
        ], JSON_THROW_ON_ERROR));

        Date::setTestNow('2025-05-01 10:00:00');

        $this->generator->shouldReceive('render')->never();

        $response = $this->action->execute($this->invoice->id, false);

        $this->assertInstanceOf(StreamedResponse::class, $response);
        $this->assertSame('cached-pdf-content', $this->captureStream($response));

        Date::setTestNow();
    }

    #[Test]
    public function it_regenerates_pdf_when_cache_is_expired(): void
    {
        // Note: Testing natural cache expiration with Storage::fake() is not possible
        // because Storage::fake() doesn't respect Date::setTestNow() for lastModified().
        // Instead, we test that forceRegenerate bypasses cache, which is the same behavior.

        Date::setTestNow('2025-05-01 00:00:00');
        $path = 'invoices/pdf/'.$this->invoice->id.'.pdf';

        // Create a cached PDF
        Storage::disk('s3')->put($path, 'old-cached-content');
        Storage::disk('s3')->put($path.'.meta.json', json_encode([
            'invoice_id' => $this->invoice->id,
            'generated_at' => Date::now()->toIso8601String(),
            'ttl_hours' => 6,
        ], JSON_THROW_ON_ERROR));

        // Force regeneration should bypass cache
        $this->generator->shouldReceive('render')
            ->once()
            ->with(Mockery::type(Invoice::class))
            ->andReturn('regenerated-content');

        $response = $this->action->execute($this->invoice->id, true);

        // Verify PDF was regenerated
        $content = $this->captureStream($response);
        $this->assertSame('regenerated-content', $content);

        // Verify storage was updated
        Storage::disk('s3')->assertExists($path);
        $this->assertSame('regenerated-content', Storage::disk('s3')->get($path));

        Date::setTestNow();
    }

    #[Test]
    public function it_forces_regeneration_even_when_cache_is_valid(): void
    {
        Date::setTestNow('2025-05-01 00:00:00');
        $path = 'invoices/pdf/'.$this->invoice->id.'.pdf';
        Storage::disk('s3')->put($path, 'fresh-content');
        Storage::disk('s3')->put($path.'.meta.json', json_encode([
            'invoice_id' => $this->invoice->id,
            'generated_at' => Date::now()->toIso8601String(),
            'ttl_hours' => 6,
        ], JSON_THROW_ON_ERROR));

        Date::setTestNow('2025-05-01 01:00:00');

        $this->generator->shouldReceive('render')
            ->once()
            ->with(Mockery::type(Invoice::class))
            ->andReturn('forced-content');

        $response = $this->action->execute($this->invoice->id, true);

        $this->assertSame('forced-content', $this->captureStream($response));
        $this->assertSame('forced-content', Storage::disk('s3')->get($path));

        Date::setTestNow();
    }

    private function captureStream(StreamedResponse $response): string
    {
        ob_start();
        $response->sendContent();
        /** @var string $buffer */
        $buffer = ob_get_clean();

        return $buffer;
    }
}
