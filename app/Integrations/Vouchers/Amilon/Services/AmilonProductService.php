<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Vouchers\Amilon\DTO\ProductDTO;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Models\Financer;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmilonProductService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The base URL for the Amilon API.
     */
    protected string $baseUrl;

    /**
     * The cache key for storing products.
     */

    /**
     * The default country key for this financer.
     */
    protected string $culture = 'PRT';

    /**
     * The cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    protected AmilonAuthService $authService;

    /**
     * Create a new AmilonService instance.
     */
    public function __construct(AmilonAuthService $authService)
    {
        $this->authService = $authService;
        $apiUrl = config('services.amilon.api_url');
        $this->baseUrl = (is_string($apiUrl) ? $apiUrl : '').'/b2bwebapi/v1';
    }

    /**
     * Get the list of products for a specific merchant from Amilon API or cache.
     *
     * @param  string  $merchantId  The merchant ID
     * @param  string  $culture  The culture/language for API request (default: pt-PT)
     * @param  bool  $forceApiCall  Force API call, bypassing DB and cache (for sync operations)
     * @return Collection<int, Product> The list of products
     */
    public function getProducts(string $merchantId, string $culture = 'pt-PT', bool $forceApiCall = false): Collection
    {
        // If not forcing API call, check existing sources
        if (! $forceApiCall) {
            // Priority 1: Check database first
            $productsFromDb = Product::where('merchant_id', $merchantId)->get();

            if ($productsFromDb->isNotEmpty()) {
                return $productsFromDb;
            }

        }

        try {
            $token = $this->authService->getAccessToken();

            $contractId = config('services.amilon.contrat_id');
            $contractId = is_string($contractId) ? $contractId : '';

            $url = "{$this->baseUrl}/contracts/{$contractId}/{$culture}/products/complete";
            $response = Http::withToken($token)
                ->get($url);

            // Log automatique de l'appel API
            $responseJson = $response->json();
            $this->logApiCall(
                'GET',
                "/contracts/{$contractId}/{$culture}/products/complete",
                $response->status(),
                is_array($responseJson) ? $responseJson : []
            );

            if ($response->successful()) {
                $products = $response->json();
                // Update or create products in database
                /** @var array<int, array<string, mixed>> $productsArray */
                $productsArray = is_array($products) ? $products : [];

                // Filter and process only products for this merchant
                $filteredProducts = array_filter($productsArray, function (array $product) use ($merchantId): bool {
                    return array_key_exists('MerchantCode', $product) && $product['MerchantCode'] === $merchantId;
                });

                return $this->upsertProductsDatabase($filteredProducts, $merchantId);
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                $response = Http::withToken($token)
                    ->get($url);
                // Log automatique du retry
                $retryResponseJson = $response->json();
                $this->logApiCall(
                    'GET',
                    "/contracts/{$contractId}/{$culture}/products/complete (retry)",
                    $response->status(),
                    is_array($retryResponseJson) ? $retryResponseJson : []
                );

                if ($response->successful()) {
                    $products = $response->json();

                    /** @var array<int, array<string, mixed>> $productsArray */
                    $productsArray = is_array($products) ? $products : [];

                    // Filter and process only products for this merchant
                    $filteredProducts = array_filter($productsArray, function (array $product) use ($merchantId): bool {
                        return array_key_exists('MerchantCode', $product) && $product['MerchantCode'] === $merchantId;
                    });

                    return $this->upsertProductsDatabase($filteredProducts, $merchantId);
                }
            }

            // If API request failed, log the error and return fallback data
            Log::error('Failed to fetch products from Amilon API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getFallbackProducts($merchantId);
        } catch (Exception $e) {
            // Log any exceptions and return fallback data
            Log::error('Exception while fetching products from Amilon API', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackProducts($merchantId);
        }
    }

    /**
     * Get products for a specific financer and merchant.
     * Maps the financer's country to the appropriate culture for the API request.
     * Database is checked first before making API calls.
     *
     * @param  Financer  $financer  The financer model
     * @param  string  $merchantId  The merchant ID
     * @return Collection<int, Product> The list of products
     */
    public function getProductsForFinancer(Financer $financer, string $merchantId): Collection
    {
        // Map country code to culture
        $culture = $this->mapCountryToCulture($financer->division->country ?? 'pt-PT');

        return $this->getProducts(merchantId: $merchantId, culture: $culture, forceApiCall: false);
    }

    /**
     * Map country code to Amilon API culture format.
     *
     * @param  string  $countryCode  The ISO country code
     * @return string The culture string for the API
     */
    protected function mapCountryToCulture(string $countryCode): string
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

    /**
     * Update or create products in the database.
     *
     * @param  array<int, array<string, mixed>>  $products  The products to update or create
     * @param  string  $merchantIdOrCulture  The merchant ID or culture/country identifier
     * @return Collection<int, Product>
     */
    public function upsertProductsDatabase(array $products, string $merchantIdOrCulture): Collection
    {
        $productsCollection = collect();
        $merchantDiscounts = collect();

        // Pre-load all merchants by merchant_id for efficient lookups
        foreach ($products as $product) {
            $availableAmounts = config('services.amilon.available_amounts');

            // Check price in available amounts - use loose comparison for floats
            $priceInList = false;
            if (is_array($availableAmounts) && array_key_exists('Price', $product)) {
                foreach ($availableAmounts as $amount) {
                    $productPrice = is_numeric($product['Price']) ? (float) $product['Price'] : 0.0;
                    $amountValue = is_numeric($amount) ? (float) $amount : 0.0;
                    if (abs($productPrice - $amountValue) < 0.01) {
                        $priceInList = true;
                        break;
                    }
                }
            }

            if ($priceInList && array_key_exists('MerchantCode', $product) && $product['MerchantCode'] == $merchantIdOrCulture) {

                // If parameter looks like a merchant ID, use it as the merchant_id for all products
                // Otherwise, extract merchant ID from product data
                if (preg_match('/^[A-Z0-9_]+$/', $merchantIdOrCulture)) {
                    // Parameter is actually a merchant_id (e.g., RETAILER123)
                    $product['merchant_id'] = $merchantIdOrCulture;
                } else {
                    // Extract merchant ID from product data - check both MerchantId and MerchantCode
                    $merchantId = $product['MerchantId'] ?? $product['MerchantCode'] ?? null;
                    if ($merchantId !== null) {
                        $product['merchant_id'] = $merchantId;
                    }
                }

                $productDTO = ProductDTO::fromArray($product);
                try {
                    $productModel = Product::updateOrCreateFromDTO($productDTO);
                    // Load category relation to avoid N+1 when accessed later
                    $productModel->load('category');
                    $productsCollection->push($productModel);

                    // Group discounts by merchant_id for average calculation
                    if ($productDTO->discount !== null && $productDTO->merchant_id) {
                        $merchantDiscounts->push([
                            'merchant_id' => $productDTO->merchant_id,
                            'discount' => $productDTO->discount,
                        ]);
                    }
                } catch (Exception $e) {
                    Log::error('Failed to create/update product', [
                        'error' => $e->getMessage(),
                        'product_code' => $productDTO->productCode,
                        'merchant_id' => $productDTO->merchant_id,
                        'trace' => $e->getTraceAsString(),
                    ]);

                    // Don't throw the exception, just skip this product
                    // This allows processing to continue even if one product fails
                    continue;
                }
            }

        }

        // Update average discount for each merchant
        $this->updateMerchantAverageDiscounts($merchantDiscounts);

        return $productsCollection;
    }

    /**
     * Get fallback data for products when API is down.
     *
     * @param  string  $merchantIdOrCulture  The merchant ID or culture/country identifier
     * @return Collection<int, Product> The fallback products
     */
    protected function getFallbackProducts(string $merchantIdOrCulture): Collection
    {
        // If it's a merchant ID, filter by merchant
        if (preg_match('/^[A-Z0-9_]+$/', $merchantIdOrCulture)) {
            $products = Product::with('category')
                ->where('merchant_id', $merchantIdOrCulture)
                ->get();
        } else {
            // Otherwise get all products
            $products = Product::with('category')->get();
        }

        if ($products->isNotEmpty()) {
            return $products;
        }

        // If no products in database, return empty collection
        return collect();
    }

    /**
     * Update average discount for merchants based on their products.
     *
     * @param  Collection<int, array{merchant_id: string, discount: float}>  $merchantDiscounts
     */
    protected function updateMerchantAverageDiscounts(Collection $merchantDiscounts): void
    {
        // Group discounts by merchant_id
        $groupedDiscounts = $merchantDiscounts->groupBy('merchant_id');

        foreach ($groupedDiscounts as $merchantId => $discounts) {
            // Calculate average discount for this merchant
            $averageDiscount = $discounts->avg('discount');

            // Update merchant's average_discount
            Merchant::where('merchant_id', $merchantId)
                ->update(['average_discount' => (float) $averageDiscount * 10]);
        }
    }

    /**
     * Get the cache TTL value.
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Get the provider name for logging.
     */
    public function getProviderName(): string
    {
        return 'amilon';
    }

    /**
     * Get the API version.
     */
    public function getApiVersion(): string
    {
        return 'v1';
    }

    /**
     * Check if the service is healthy.
     */
    public function isHealthy(): bool
    {
        try {
            // Try to get products with a small limit to test the API
            $token = $this->authService->getAccessToken();

            return ! empty($token);
        } catch (Exception $e) {
            return false;
        }
    }
}
