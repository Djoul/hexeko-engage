<?php

declare(strict_types=1);

namespace App\Services\Invoicing;

use App\DTOs\Invoicing\CachedInvoicePdfDTO;
use App\Models\Invoice;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Date;
use JsonException;
use Log;

class InvoicePdfCacheService
{
    public function __construct(
        private readonly FilesystemFactory $filesystem,
        private readonly InvoicePdfGenerator $generator,
    ) {}

    public function get(string $invoiceId, bool $forceRegenerate = false): CachedInvoicePdfDTO
    {
        $invoice = Invoice::with(['items', 'recipient'])->findOrFail($invoiceId);

        $disk = $this->filesystem->disk($this->diskName());
        $path = $this->buildPath($invoiceId);
        $metaPath = $this->buildMetadataPath($path);

        $cacheEnabled = (bool) config('invoicing.pdf.cache_enabled', true);
        $ttlSeconds = $this->cacheTtlSeconds();

        if ($cacheEnabled && ! $forceRegenerate && $this->isCacheValid($disk, $path, $metaPath, $ttlSeconds)) {
            /** @var string $content */
            $content = $disk->get($path);

            Log::info('Invoice PDF served from cache', [
                'invoice_id' => $invoiceId,
                'path' => $path,
            ]);

            return new CachedInvoicePdfDTO(
                invoice: $invoice,
                content: $content,
                path: $path,
                fromCache: true,
            );
        }

        $content = $this->generator->render($invoice);

        $disk->put($path, $content);
        $disk->put($metaPath, json_encode([
            'invoice_id' => $invoiceId,
            'generated_at' => Date::now()->toIso8601String(),
            'ttl_hours' => $this->cacheTtlHours(),
        ], JSON_THROW_ON_ERROR));

        Log::info('Invoice PDF generated and cached', [
            'invoice_id' => $invoiceId,
            'path' => $path,
            'from_cache' => false,
        ]);

        return new CachedInvoicePdfDTO(
            invoice: $invoice->fresh(),
            content: $content,
            path: $path,
            fromCache: false,
        );
    }

    private function diskName(): string
    {
        /** @var string $disk */
        $disk = config('invoicing.pdf.storage_disk', 's3');

        return $disk;
    }

    private function basePath(): string
    {
        /** @var string $path */
        $path = config('invoicing.pdf.storage_path', 'invoices/pdf');

        return trim($path, '/');
    }

    private function cacheTtlHours(): int
    {
        /** @var int|float|string|null $ttl */
        $ttl = config('invoicing.pdf.cache_ttl_hours', 6);

        return (int) $ttl;
    }

    private function cacheTtlSeconds(): int
    {
        return max(0, $this->cacheTtlHours()) * 3600;
    }

    private function buildPath(string $invoiceId): string
    {
        return $this->basePath().'/'.$invoiceId.'.pdf';
    }

    private function buildMetadataPath(string $path): string
    {
        return $path.'.meta.json';
    }

    private function isCacheValid(
        FilesystemAdapter $disk,
        string $path,
        string $metaPath,
        int $ttlSeconds,
    ): bool {
        if (! $disk->exists($path) || $ttlSeconds === 0) {
            return false;
        }

        $now = Date::now();

        if ($disk->exists($metaPath)) {
            try {
                /** @var string $metaRaw */
                $metaRaw = $disk->get($metaPath);
                $meta = json_decode($metaRaw, true, flags: JSON_THROW_ON_ERROR);

                $generatedAt = isset($meta['generated_at'])
                    ? Date::parse($meta['generated_at'])
                    : null;

                if ($generatedAt !== null && $generatedAt->diffInRealSeconds($now) <= $ttlSeconds) {
                    return true;
                }
            } catch (JsonException) {
                // Ignore and fallback to lastModified check below
            }
        }

        $lastModified = $disk->lastModified($path);
        $generatedAt = Date::createFromTimestamp($lastModified);

        return $generatedAt->diffInRealSeconds($now) <= $ttlSeconds;
    }
}
