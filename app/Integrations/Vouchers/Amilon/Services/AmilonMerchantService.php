<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Vouchers\Amilon\DTO\MerchantDTO;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AmilonMerchantService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The base URL for the Amilon API.
     */
    protected string $baseUrl;

    /**
     * The cache key for storing products.
     */
    protected string $cacheKeyMerchants = 'amilon_merchants';

    /**
     * The default country key for this financer.
     */
    protected string $culture = 'pt-PT';

    /**
     * The cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    protected AmilonAuthService $authService;

    /**
     * Create a new AmilonService instance.
     */
    public function __construct(
        AmilonAuthService $authService,
        private readonly CreateOrUpdateTranslatedCategoryAction $createCategoryAction
    ) {
        $this->authService = $authService;
        $apiUrl = config('services.amilon.api_url');
        $contractId = config('services.amilon.contrat_id');
        $this->baseUrl = (is_string($apiUrl) ? $apiUrl : '').'/b2bwebapi/v1';
        $this->cacheKeyMerchants .= '_'.(is_string($contractId) ? $contractId : '');
    }

    /**
     * Get the list of merchants from Amilon API or cache.
     *
     * @param  string|null  $culture  The culture/language for the API call
     * @param  bool  $forceApiCall  Force an API call even if cache exists
     */
    public function getMerchants(?string $culture = null, bool $forceApiCall = false): Collection
    {
        // Use default culture if not provided
        $culture = $culture ?? $this->culture;

        // Skip DB and cache check if forcing API call
        if (! $forceApiCall) {
            // Priority 1: Check database first
            // Include both country-specific merchants and EUR (Eurozone) merchants
            $merchantsFromDb = Merchant::with('categories')
                ->where(function ($query): void {
                    $query->where('country', 'PRT')
                        /* ->orWhere('country', 'EUR') */;
                })
                ->get();

            if ($merchantsFromDb->isNotEmpty()) {
                // Cache the merchants for future use

                return $merchantsFromDb;
            }

        }

        // Priority 2: Fetch from API (if forceApiCall or no data in DB/cache)

        try {
            $token = $this->authService->getAccessToken();

            $contract_id = config('services.amilon.contrat_id');
            $contract_id = is_string($contract_id) ? $contract_id : '';

            $url = "{$this->baseUrl}/contracts/{$contract_id}/{$culture}/retailers";

            $response = Http::withToken($token)
                ->timeout(5)
                ->get($url);

            // Debug logging removed for production
            // Log automatique de l'appel API
            $responseData = $response->json();
            $this->logApiCall(
                'GET',
                "/contracts/{$contract_id}/{$culture}/retailers",
                $response->status(),
                is_array($responseData) ? $responseData : []
            );

            if ($response->successful()) {
                $merchants = $response->json();
                /** @var array<int, array<string, mixed>> $merchantsArray */
                $merchantsArray = is_array($merchants) ? $merchants : [];

                return $this->upsertMerchantsDatabase($merchantsArray);
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                $uri = "{$this->baseUrl}/contracts/{$contract_id}/{$culture}/retailers";

                $response = Http::withToken($token)
                    ->get($uri);

                // Log automatique du retry
                $responseData = $response->json();
                $this->logApiCall(
                    'GET',
                    "/contracts/{$contract_id}/{$culture}/retailers (retry)",
                    $response->status(),
                    is_array($responseData) ? $responseData : []
                );

                if ($response->successful()) {
                    $merchants = $response->json();

                    /** @var array<int, array<string, mixed>> $merchantsArray */
                    $merchantsArray = is_array($merchants) ? $merchants : [];

                    return $this->upsertMerchantsDatabase($merchantsArray);
                }
            }
            //
            // If API request failed, log the error and return fallback data
            Log::error('Failed to fetch products from Amilon API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getFallbackData();
        } catch (Exception $e) {
            // Log any exceptions and return fallback data
            Log::error('Exception while fetching products from Amilon API', [
                'exception' => $e->getMessage(),
            ]);

            return $this->getFallbackData();
        }
    }

    /**
     * Update or create merchants in the database.
     *
     * @param  array<int, array<string, mixed>>  $merchants
     * @return Collection<int, Merchant>
     */
    public function upsertMerchantsDatabase(array $merchants): Collection
    {
        // CRITICAL: Define Twitch identifiers to exclude (Apple App Store policy)
        $twitchId = '0199bdd3-32b1-72be-905b-591833b488cf';
        $twitchMerchantId = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

        $merchantsCollection = collect();
        foreach ($merchants as $merchantArray) {
            // Skip Twitch merchant during sync (triple check: ID, merchant_id, name)
            $merchantIdFromApi = $merchantArray['id'] ?? null;
            $merchantMerchantId = $merchantArray['RetailerId'] ?? null;
            /** @var string $merchantName */
            $merchantName = $merchantArray['Name'] ?? '';
            if ($merchantIdFromApi === $twitchId) {
                // Skip this merchant - do not sync to database
                continue;
            }
            if ($merchantMerchantId === $twitchMerchantId) {
                // Skip this merchant - do not sync to database
                continue;
            }
            if (stripos($merchantName, 'twitch') !== false) {
                // Skip this merchant - do not sync to database
                continue;
            }

            $merchantDTO = MerchantDTO::fromArray($merchantArray);
            $merchant = Merchant::updateOrCreateFromDTO($merchantDTO);

            // Handle category if present in API response
            if (array_key_exists('category', $merchantArray) && ! empty($merchantArray['category'])) {
                $categoryName = $merchantArray['category'];

                // Find existing category by checking all translations
                $existingCategory = Category::all()->first(function (Category $category) use ($categoryName): bool {
                    $translations = $category->getTranslations('name');

                    return in_array($categoryName, $translations, true);
                });

                if ($existingCategory) {
                    $category = $existingCategory;
                } else {
                    // Create new category with translations using the action
                    $result = $this->createCategoryAction->execute((string) Str::uuid(), (string) $categoryName);
                    $category = $result['category'];
                }

                // Sync category with merchant
                $merchant->categories()->sync([$category->id]);
            }

            $merchant->load('categories');
            $merchantsCollection->push($merchant);
        }

        return $merchantsCollection;
    }

    /**
     * Get the cache TTL value.
     */
    public function getCacheTtl(): int
    {
        return $this->cacheTtl;
    }

    /**
     * Calculate available amounts for merchants based on user budget.
     *
     * @param  Collection<int, Merchant>  $merchants
     * @return Collection<int, Merchant>
     */
    public function calculateAvailableAmounts(Collection $merchants, float $userBudget): Collection
    {
        return $merchants->map(function (Merchant $merchant) use ($userBudget): Merchant {
            $availableAmounts = [];
            $standardAmounts = [10, 25, 50, 100, 250, 500];

            foreach ($standardAmounts as $amount) {
                if ($amount <= $userBudget) {
                    $availableAmounts[] = $amount;
                }
            }

            // Add custom amount if user budget is not in standard amounts
            if (! in_array($userBudget, $standardAmounts) && $userBudget > 0) {
                $availableAmounts[] = $userBudget;
                sort($availableAmounts);
            }

            $merchant->available_amounts = $availableAmounts;

            return $merchant;
        });
    }

    /**
     * Get fallback data when API is down.
     */
    protected function getFallbackData(): Collection
    {
        // Try to get merchants from database first
        $merchants = Merchant::with('categories')->get();

        if ($merchants->isNotEmpty()) {
            return $merchants;
        }

        // If no merchants in database, return hardcoded fallback data
        return collect([
            new Merchant(
                [
                    'name' => 'Fallback Merchant 1',
                    'merchant_id' => 'FALLBACK001',
                    'description' => 'Fallback merchant when API is down',
                    'image_url' => null,
                ]
            ),
            new Merchant(
                [
                    'name' => 'Fallback Merchant 2',
                    'merchant_id' => 'FALLBACK002',
                    'description' => 'Fallback merchant when API is down',
                    'image_url' => null,
                ],
            ),
        ]);
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
            $token = $this->authService->getAccessToken();

            return ! empty($token);
        } catch (Exception $e) {
            return false;
        }
    }
}
