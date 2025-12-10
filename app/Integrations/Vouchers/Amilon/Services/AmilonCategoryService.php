<?php

namespace App\Integrations\Vouchers\Amilon\Services;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Actions\Integrations\Vouchers\Amilon\SendNewCategorySlackNotificationAction;
use App\Documentation\ThirdPartyApis\Concerns\LogsApiCalls;
use App\Documentation\ThirdPartyApis\Contracts\ThirdPartyServiceInterface;
use App\Integrations\Vouchers\Amilon\Models\Category;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AmilonCategoryService implements ThirdPartyServiceInterface
{
    use LogsApiCalls;

    /**
     * The base URL for the Amilon API.
     */
    protected string $baseUrl;

    /**
     * The cache key for storing categories.
     */
    protected string $cacheKey = 'amilon_categories';

    /**
     * The cache TTL in seconds (24 hours).
     */
    protected int $cacheTtl = 86400;

    /**
     * The default country key for this financer.
     */
    protected string $culture = 'pt-PT';

    /**
     * The auth service for getting API tokens.
     */
    protected AmilonAuthService $authService;

    /**
     * Create a new AmilonCategoryService instance.
     */
    public function __construct(
        AmilonAuthService $authService,
        private readonly CreateOrUpdateTranslatedCategoryAction $createCategoryAction,
        private readonly SendNewCategorySlackNotificationAction $slackNotificationAction
    ) {
        $this->authService = $authService;
        $apiUrl = config('services.amilon.api_url');
        $this->baseUrl = (is_string($apiUrl) ? $apiUrl : '').'/b2bwebapi/v1';
    }

    /**
     * Get all categories from the database or API.
     *
     * @return Collection<int, array{CategoryId: string, CategoryName: string}> Collection of categories with id and name
     */
    public function getCategories(): Collection
    {

        try {
            // Always try to fetch from API first for fresh data
            $categories = $this->fetchCategoriesFromApi();

            return $categories;
        } catch (Exception $e) {
            // Log any exceptions and return empty collection
            Log::error('Exception while fetching categories', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return collect([]);
        }
    }

    /**
     * Fetch categories from the API and store them in the database.
     *
     * @return Collection<int, array{CategoryId: string, CategoryName: string}> Collection of categories with id and name
     */
    public function fetchCategoriesFromApi(): Collection
    {
        try {
            $token = $this->authService->getAccessToken();

            $url = "{$this->baseUrl}/retailers/categories";

            $response = Http::withToken($token)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->get($url);

            // Log automatique de l'appel API
            $responseJson = $response->json();
            $this->logApiCall(
                'GET',
                '/retailers/categories',
                $response->status(),
                is_array($responseJson) ? $responseJson : []
            );

            if ($response->successful()) {
                $categories = $response->json();

                // Store categories in database
                /** @var array<int, array<string, mixed>> $categoriesArray */
                $categoriesArray = is_array($categories) ? $categories : [];

                Log::info('Initial API call successful', ['count' => count($categoriesArray)]);

                return $this->upsertCategoriesDatabase($categoriesArray);
            }

            // If authentication failed, try to refresh the token and retry
            if ($response->status() === 401) {
                Log::warning('Authentication failed, refreshing token and retrying');

                // Refresh token and retry
                $token = $this->authService->refreshToken();

                $response = Http::withToken($token)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(5)
                    ->get($url);

                // Log automatique du retry
                $retryResponseJson = $response->json();
                $this->logApiCall(
                    'GET',
                    '/retailers/categories (retry)',
                    $response->status(),
                    is_array($retryResponseJson) ? $retryResponseJson : []
                );

                if ($response->successful()) {
                    $categories = $response->json();

                    /** @var array<int, array<string, mixed>> $categoriesArray */
                    $categoriesArray = is_array($categories) ? $categories : [];

                    Log::info('Categories fetched from API', ['count' => count($categoriesArray)]);

                    return $this->upsertCategoriesDatabase($categoriesArray);
                }
            }

            // If API request failed, log the error and return fallback data
            Log::error('Failed to fetch categories from Amilon API', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->getFallbackCategories();
        } catch (Exception $e) {
            // Log any exceptions and return fallback data
            Log::error('Exception while fetching categories from Amilon API', [
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->getFallbackCategories();
        }
    }

    /**
     * Update or create categories in the database.
     *
     * @param  array<int, array<string, mixed>>  $categories  The categories to update or create
     * @return Collection<int, array{CategoryId: string, CategoryName: string}> The collection of categories
     */
    public function upsertCategoriesDatabase(array $categories): Collection
    {
        $categoriesCollection = collect();
        try {
            DB::beginTransaction();
            foreach ($categories as $categoryData) {
                $categoryId = is_scalar($categoryData['CategoryId']) ? (string) $categoryData['CategoryId'] : '';
                $defaultName = is_scalar($categoryData['CategoryName']) ? (string) $categoryData['CategoryName'] : '';

                // Use action to create or update category with translations
                $result = $this->createCategoryAction->execute($categoryId, $defaultName);
                $category = $result['category'];
                $wasCreated = $result['wasCreated'];

                // Send Slack notification if category was newly created
                if ($wasCreated) {
                    $this->slackNotificationAction->execute($category);
                }

                $categoriesCollection->push([
                    'CategoryId' => $category->id,
                    'CategoryName' => (string) $category->getTranslation('name', app()->getLocale()),
                ]);
            }

            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Failed to upsert categories to database', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'categories' => $categories,
            ]);
            throw $e;
        }

        return $categoriesCollection;
    }

    /**
     * Get fallback data for categories when API is down.
     *
     * @return Collection<int, array{CategoryId: string, CategoryName: string}> The fallback categories
     */
    protected function getFallbackCategories(): Collection
    {
        try {
            // Try to get categories from database first
            $categories = Category::all();

            if ($categories->isNotEmpty()) {
                return $categories->map(function ($category): array {
                    /** @var Category $category */
                    return [
                        'CategoryId' => $category->id,
                        'CategoryName' => (string) $category->getTranslation('name', app()->getLocale()),
                    ];
                });
            }
        } catch (Exception $e) {
            // If there's a database error (like transaction aborted), log it and return empty
            Log::error('Failed to get fallback categories from database', [
                'error' => $e->getMessage(),
            ]);
        }

        // If no categories in database or error occurred, return empty collection
        return collect([]);
    }

    /**
     * Get category by ID.
     */
    public function getCategoryById(string $categoryId): ?Category
    {
        return Category::find($categoryId);
    }

    /**
     * Validate that a category exists.
     */
    public function validateCategoryExists(string $categoryId): bool
    {
        return Category::where('id', $categoryId)->exists();
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
            $token = $this->authService->getAccessToken();

            return ! empty($token);
        } catch (Exception $e) {
            return false;
        }
    }
}
