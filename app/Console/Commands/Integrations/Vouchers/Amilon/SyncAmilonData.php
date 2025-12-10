<?php

declare(strict_types=1);

namespace App\Console\Commands\Integrations\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonCategoryService;
use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncAmilonData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'amilon:sync-data
                            {--force : Force sync even if cache is fresh}
                            {--merchants : Sync only merchants}
                            {--categories : Sync only categories}
                            {--products : Sync only products}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync Amilon data: categories, merchants, and products';

    public function __construct(
        private readonly AmilonCategoryService $categoryService,
        private readonly AmilonMerchantService $merchantService,
        private readonly AmilonProductService $productService
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Increase memory limit for this command (API responses can be very large)
        // Related to ENGAGE-MAIN-API-A1: Memory exhaustion when syncing products
        $originalMemoryLimit = ini_get('memory_limit');
        $currentUsage = memory_get_usage(true);
        $requiredMemory = max(512 * 1024 * 1024, $currentUsage * 2); // At least 512M or 2x current usage
        $newLimit = ceil($requiredMemory / (1024 * 1024)).'M';

        ini_set('memory_limit', $newLimit);

        $this->info("Memory limit adjusted from {$originalMemoryLimit} to {$newLimit} for sync operation");

        Log::info('Amilon sync started', [
            'original_memory_limit' => $originalMemoryLimit,
            'new_memory_limit' => $newLimit,
            'current_usage_mb' => round($currentUsage / 1024 / 1024, 2),
        ]);

        $enabledCountries = config('services.amilon.enabled_countries', []);

        if (! is_array($enabledCountries)) {
            $this->error('Invalid enabled_countries configuration');

            // Restore original memory limit before returning
            ini_set('memory_limit', $originalMemoryLimit);

            return Command::FAILURE;
        }

        // Clear only Amilon cache
        $this->info('Clearing Amilon cache...');

        $this->info('Starting Amilon data synchronization...');

        try {
            //            DB::beginTransaction();

            $syncAll = ! $this->option('merchants') && ! $this->option('categories') && ! $this->option('products');

            if ($syncAll || $this->option('categories')) {
                $this->syncCategories();
            }

            foreach ($enabledCountries as $country) {
                if (! is_string($country)) {
                    $this->warn('Invalid country configuration, skipping...');

                    continue;
                }

                if ($syncAll || $this->option('merchants')) {
                    $this->syncMerchants($country);
                }

                if ($syncAll || $this->option('products')) {
                    $this->syncProducts($country);
                }
            }

            //            DB::commit();
            $this->info('Amilon data synchronization completed successfully!');

            // Restore original memory limit
            ini_set('memory_limit', $originalMemoryLimit);
            $this->info('Memory limit restored to '.$originalMemoryLimit);

            return Command::SUCCESS;
        } catch (Exception $e) {
            //            DB::rollBack();
            $this->error('Error during synchronization: '.$e->getMessage());
            Log::error('Amilon sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_peak_usage(true) / 1024 / 1024 .' MB',
            ]);

            // Restore original memory limit even on failure
            ini_set('memory_limit', $originalMemoryLimit);

            return Command::FAILURE;
        }
    }

    private function syncCategories(): void
    {
        $this->info('Syncing categories... ');
        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        try {
            $categories = $this->categoryService->getCategories();
            $progressBar->advance();

            if ($categories->isNotEmpty()) {
                /** @var array<int, array<string, mixed>> $categoriesArray */
                $categoriesArray = $categories->toArray();

                $this->categoryService->upsertCategoriesDatabase($categoriesArray);
                $this->info("\n✓ Synced {$categories->count()} categories");
            } else {
                $this->warn("\n⚠ No categories found to sync");
            }
        } finally {
            $progressBar->finish();
        }
    }

    private function syncMerchants(string $country): void
    {
        $this->info("\nSyncing merchants...");
        $progressBar = $this->output->createProgressBar();
        $progressBar->start();

        try {
            // Force API call to get fresh data during sync
            $merchants = $this->merchantService->getMerchants($country, true);
            $progressBar->advance();

            if ($merchants->isNotEmpty()) {
                /** @var array<int, array<string, mixed>> $merchantsArray */
                /** @var array<int, array{id: int, merchant_id: string, name: string, status: string, country_iso: string, affiliate_info: array<string, mixed>|null, logo: string|null, is_active: bool, categories?: array<int, int>}> $merchantsArray */
                $merchantsArray = $merchants->map(function ($merchant): array {
                    if (! $merchant instanceof Merchant) {
                        throw new Exception('Invalid merchant type');
                    }

                    return $merchant->toArray();
                })->toArray();
                $this->merchantService->upsertMerchantsDatabase($merchantsArray);
                $this->info("\n✓ Synced {$merchants->count()} merchants");
            } else {
                $this->warn("\n⚠ No merchants found to sync");
            }
        } finally {
            $progressBar->finish();
        }
    }

    private function syncProducts(string $country): void
    {
        $this->info("\nSyncing all products for country: {$country}");

        $memoryBefore = memory_get_usage(true) / 1024 / 1024;
        $this->info('Memory usage before sync: '.round($memoryBefore, 2).' MB');

        $merchants = Merchant::all();

        if ($merchants->isEmpty()) {
            $this->warn('No merchants found. Please sync merchants first.');

            return;
        }

        // Map country to culture
        $culture = $this->mapCountryToCulture($country);

        $totalProducts = 0;
        $progressBar = $this->output->createProgressBar($merchants->count());
        $progressBar->start();

        try {
            // Sync products for each merchant
            foreach ($merchants as $merchant) {
                $this->info("\n  Syncing products for merchant: {$merchant->name} (Culture: {$culture})");

                $memoryBeforeMerchant = memory_get_usage(true) / 1024 / 1024;

                // Get products for this merchant and culture
                // Force API call to get fresh data (bypass DB and cache)
                $products = $this->productService->getProducts($merchant->merchant_id, $culture, true);

                $memoryAfterMerchant = memory_get_usage(true) / 1024 / 1024;
                $memoryUsedForMerchant = $memoryAfterMerchant - $memoryBeforeMerchant;

                $progressBar->advance();

                if ($products->isNotEmpty()) {
                    $totalProducts += $products->count();
                    $this->info("    ✓ Synced {$products->count()} products for {$merchant->name} (Memory: +".round($memoryUsedForMerchant, 2).' MB)');

                    // Extract unique category IDs from merchant's products
                    /** @var array<int> $categoryIds */
                    $categoryIds = $products->pluck('category_id')
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    // Sync merchant's categories using the many-to-many relationship
                    if (! empty($categoryIds)) {
                        $merchant->categories()->sync($categoryIds);
                        $this->info("    ✓ Synced categories for merchant: {$merchant->name}");
                    }

                    // Clear products from memory after processing to reduce memory usage
                    unset($products);
                    gc_collect_cycles();
                } else {
                    $this->info("    ⚠ No products found for {$merchant->name}");
                }
            }

            $memoryAfter = memory_get_usage(true) / 1024 / 1024;
            $memoryPeak = memory_get_peak_usage(true) / 1024 / 1024;

            $this->info("\n✓ Total products synced: {$totalProducts}");
            $this->info('Memory usage after sync: '.round($memoryAfter, 2).' MB');
            $this->info('Peak memory usage: '.round($memoryPeak, 2).' MB');

        } catch (Exception $e) {
            $this->error("\n✗ Error syncing products: {$e->getMessage()}");
            Log::error('Product sync failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'memory_usage' => memory_get_peak_usage(true) / 1024 / 1024 .' MB',
            ]);
        } finally {
            $progressBar->finish();
        }

        $this->info("\n✓ Product synchronization completed for country: {$country}");
    }

    /**
     * Map country code to Amilon API culture format.
     */
    private function mapCountryToCulture(string $countryCode): string
    {
        $mappings = [
            'IT' => 'it-IT',
            'DK' => 'da-DK',
            'GB' => 'en-GB',
            'FR' => 'fr-FR',
            'ES' => 'es-ES',
            'DE' => 'de-DE',
            'NL' => 'nl-NL',
            'NO' => 'nn-NO',
            'PL' => 'pl-PL',
            'PT' => 'pt-PT',
        ];

        return $mappings[strtoupper($countryCode)] ?? 'pt-PT';
    }
}
