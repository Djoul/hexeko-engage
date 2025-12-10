<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Http\Resources\MerchantResource;
use App\Integrations\Vouchers\Amilon\Http\Resources\MerchantResourceCollection;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonMerchantService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

#[Group('Modules/Vouchers/Amilon')]
class MerchantController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonMerchantService $amilonService
    ) {}

    /**
     * Get a list of merchants
     *
     * Retrieves all merchants with optional filtering, sorting, and pagination.
     *
     * @response MerchantResourceCollection
     * @response 400 scenario="Invalid parameters" {
     *   "error": "Invalid pagination parameters",
     *   "message": "per_page must be between 1 and 100"
     * }
     * @response 500 scenario="Server error" {
     *   "error": "Failed to fetch merchants",
     *   "message": "An error occurred while fetching the merchant list"
     * }
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('category', description: 'Filter by category.', type: 'string', example: 'Electronics')]
    #[QueryParameter('search', description: 'Search by merchant name.', type: 'string', example: 'Fnac')]
    #[QueryParameter('sort', description: 'Sort by field (name).', type: 'string', example: 'name')]
    #[QueryParameter('order', description: 'Sort order (asc, desc).', type: 'string', example: 'asc')]
    public function index(Request $request): JsonResponse|MerchantResourceCollection
    {
        try {
            // Validate pagination parameters
            $perPageParam = $request->per_page;
            $pageParam = $request->page;
            $categoryParam = $request->category;
            $searchParam = $request->search;
            $sortParam = $request->sort;
            $orderParam = $request->order;

            // Validate per_page
            if ($perPageParam !== null && (! is_numeric($perPageParam) || (int) $perPageParam < 1 || (int) $perPageParam > 100)) {
                return response()->json([
                    'error' => 'Invalid pagination parameters',
                    'message' => 'per_page must be between 1 and 100',
                ], 400);
            }

            // Validate page
            if ($pageParam !== null && (! is_numeric($pageParam) || (int) $pageParam < 1)) {
                return response()->json([
                    'error' => 'Invalid pagination parameters',
                    'message' => 'page must be greater than 0',
                ], 400);
            }

            // Validate category
            if ($categoryParam !== null && ! is_string($categoryParam)) {
                return response()->json([
                    'error' => 'Invalid category parameter',
                    'message' => 'category must be a string',
                ], 400);
            }

            // Validate sort parameter
            if ($sortParam !== null && ! in_array($sortParam, ['name', 'category'])) {
                return response()->json([
                    'error' => 'Invalid sort parameter',
                    'message' => 'sort must be one of: name, category',
                ], 400);
            }

            // Validate order parameter
            if ($orderParam !== null && ! in_array($orderParam, ['asc', 'desc'])) {
                return response()->json([
                    'error' => 'Invalid order parameter',
                    'message' => 'order must be one of: asc, desc',
                ], 400);
            }

            // Set defaults
            $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
            $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;
            $sort = is_string($sortParam) ? $sortParam : 'name';
            $order = $orderParam ?? 'asc';

            $merchants = $this->amilonService->getMerchants('pt-PT', forceApiCall: false);

            /** @var Collection<int, Merchant> $merchantCollection */
            $merchantCollection = $merchants;

            // CRITICAL: Exclude Twitch from all merchants (Apple App Store policy)
            // Twitch is a digital product and violates Apple's in-app purchase policy
            $merchantCollection = $merchantCollection->filter(function ($merchant): bool {
                /** @var Merchant $merchant */
                $twitchId = '0199bdd3-32b1-72be-905b-591833b488cf';
                $twitchMerchantId = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

                // Triple check: exclude by ID, merchant_id, or name
                return $merchant->id !== $twitchId
                    && $merchant->merchant_id !== $twitchMerchantId
                    && stripos($merchant->name ?? '', 'twitch') === false;
            });

            // Apply category filter
            if (! in_array($categoryParam, [null, '', '0'], true)) {
                $merchantCollection = $merchantCollection->filter(function ($merchant) use ($categoryParam): bool {
                    /** @var Merchant $merchant */
                    // Check if any of the merchant's categories match the filter
                    return $merchant->categories->contains(function ($category) use ($categoryParam): bool {
                        /** @var Category $category */
                        return stripos($category->name ?? '', $categoryParam) !== false;
                    });
                });
            }

            // Apply search filter
            if ($searchParam && is_string($searchParam)) {
                $merchantCollection = $merchantCollection->filter(function ($merchant) use ($searchParam): bool {
                    /** @var Merchant $merchant */
                    return stripos($merchant->name, $searchParam) !== false;
                });
            }

            // Apply sorting
            if ($sort === 'category') {
                // Sort by first category name
                $merchantCollection = $merchantCollection->sortBy(function ($merchant) {
                    /** @var Merchant $merchant */
                    /** @var Category|null $firstCategory */
                    $firstCategory = $merchant->categories->first();

                    return $firstCategory ? $firstCategory->name : 'Uncategorized';
                }, SORT_REGULAR, $order === 'desc');
            } else {
                $merchantCollection = $merchantCollection->sortBy($sort, SORT_REGULAR, $order === 'desc');
            }

            $total = $merchantCollection->count();

            // Apply pagination if requested
            if ($request->has('page') || $request->has('per_page')) {
                $merchantCollection = $merchantCollection->forPage($page, $perPage);
            }

            $collection = new MerchantResourceCollection(
                $merchantCollection->filter(fn (Merchant $merchant): bool => ! empty($merchant->name) && ! empty($merchant->image_url)
                )->values()
            );

            return $collection->additional([
                'meta' => [
                    'total_items' => $total,
                ],
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching merchants', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch merchants',
                'message' => 'An error occurred while fetching the merchant list',
            ], 500);
        }
    }

    /**
     * Get merchants grouped by category
     *
     * Retrieves all merchants organized by their primary category.
     *
     * @response {
     *   "data": {
     *     "Electronics": [
     *       {
     *         "id": "123",
     *         "name": "Fnac",
     *         "category": "Electronics"
     *       }
     *     ],
     *     "Uncategorized": []
     *   }
     * }
     * @response 500 scenario="Server error" {
     *   "error": "Failed to fetch merchants by category",
     *   "message": "An error occurred while fetching merchants organized by category"
     * }
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function byCategory(): JsonResponse
    {
        try {
            $merchants = $this->amilonService->getMerchants();

            // CRITICAL: Exclude Twitch from all merchants (Apple App Store policy)
            $twitchId = '0199bdd3-32b1-72be-905b-591833b488cf';
            $twitchMerchantId = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

            $merchants = $merchants->filter(function ($merchant) use ($twitchId, $twitchMerchantId): bool {
                /** @var Merchant $merchant */
                return $merchant->id !== $twitchId
                    && $merchant->merchant_id !== $twitchMerchantId
                    && stripos($merchant->name ?? '', 'twitch') === false;
            });

            /** @var Collection<string, Collection<int, Merchant>> $merchantsByCategory */
            $merchantsByCategory = $merchants
                ->groupBy(function ($merchant): string {
                    /** @var Merchant $merchant */
                    /** @var Category|null $firstCategory */
                    $firstCategory = $merchant->categories->first();

                    return $firstCategory ? $firstCategory->name : 'Uncategorized';
                })
                ->map(function ($categoryMerchants, $categoryName) {
                    /** @var Collection<int, Merchant> $categoryMerchants */
                    /** @var string $categoryName */
                    return $categoryMerchants->map(function ($merchant) use ($categoryName): MerchantResource {
                        /** @var Merchant $merchant */
                        // Temporarily set the normalized category for the resource
                        $merchant->setAttribute('category', $categoryName);

                        return new MerchantResource($merchant);
                    });
                });

            return response()->json([
                'data' => $merchantsByCategory,
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching merchants by category', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch merchants by category',
                'message' => 'An error occurred while fetching merchants organized by category',
            ], 500);
        }
    }

    /**
     * Get a specific merchant
     *
     * Retrieves details of a single merchant by ID.
     *
     * @response MerchantResource
     * @response 404 scenario="Merchant not found" {
     *   "error": "Merchant not found",
     *   "message": "The requested merchant does not exist"
     * }
     * @response 500 scenario="Server error" {
     *   "error": "Failed to fetch merchant",
     *   "message": "An error occurred while fetching the merchant details"
     * }
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    public function show(string $id): JsonResponse|MerchantResource
    {

        try {
            // CRITICAL: Block access to Twitch merchant (Apple App Store policy)
            $twitchId = '0199bdd3-32b1-72be-905b-591833b488cf';
            $twitchMerchantId = 'a4322514-36f1-401e-af3d-6a1784a3da7a';

            if ($id === $twitchId || $id === $twitchMerchantId) {
                return response()->json([
                    'error' => 'Merchant not found or blocked',
                    'message' => 'The requested merchant does not exist',
                ], 404);
            }

            $merchant = Merchant::with('categories')->findOrFail($id);

            if (! $merchant) {
                return response()->json([
                    'error' => 'Merchant not found',
                    'message' => 'The requested merchant does not exist',
                ], 404);
            }

            // Additional name check for extra safety
            if (stripos($merchant->name ?? '', 'twitch') !== false) {
                return response()->json([
                    'error' => 'Merchant not found',
                    'message' => 'The requested merchant does not exist',
                ], 404);
            }

            return new MerchantResource($merchant);

        } catch (Exception $e) {
            Log::error('Error fetching merchant', [
                'exception' => $e->getMessage(),
                'merchant_id' => $id,
            ]);

            return response()->json([
                'error' => 'Failed to fetch merchant',
                'message' => 'An error occurred while fetching the merchant details',
            ], 500);
        }
    }
}
