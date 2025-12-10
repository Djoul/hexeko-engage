<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Http\Resources\ProductResourceCollection;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use App\Models\Financer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;

#[Group('Modules/Vouchers/Amilon')]
class ProductController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonProductService $amilonService
    ) {}

    /**
     * Get all products for a specific merchant.
     *
     * NOTE: All monetary amounts (price, net_price, discount) are returned in CENTS.
     * Example: price: 1000 = €10.00
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    public function index(Request $request, string $merchantId): JsonResponse|ProductResourceCollection
    {

        try {
            // Verify that the merchant exists
            $merchant = Merchant::find($merchantId);

            if (! $merchant) {
                return response()->json([
                    'error' => 'Merchant not found',
                    'message' => 'The specified merchant does not exist',
                ], 404);
            }

            $perPageParam = $request->per_page;
            $pageParam = $request->page;

            // Ensure proper type handling before casting
            $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
            $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

            // Get the authenticated user's financer
            $financer = Financer::where('id', activeFinancerID())->firstOrFail();

            if ($financer) {
                // Use financer-specific culture based on country
                $products = $this->amilonService->getProductsForFinancer($financer, $merchant->merchant_id);
            } else {
                // Fallback to default culture
                $products = $this->amilonService->getProducts(merchantId: $merchant->merchant_id, forceApiCall: true);
            }

            $collection = collect($products);

            // Apply pagination if requested
            if ($request->has('page') || $request->has('per_page')) {
                $paginatedCollection = $collection->forPage($page, $perPage);
                $resourceCollection = new ProductResourceCollection($paginatedCollection);
                $resourceCollection->additional([
                    'meta' => [
                        'total' => $collection->count(),
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'last_page' => $perPage > 0 ? (int) ceil($collection->count() / $perPage) : 1,
                    ],
                ]);

                return $resourceCollection;
            }

            // Default pagination for non-paginated requests
            $resourceCollection = new ProductResourceCollection($collection);
            $resourceCollection->additional([
                'meta' => [
                    'total' => $collection->count(),
                    'current_page' => 1,
                    'per_page' => $collection->count(),
                    'last_page' => 1,
                ],
            ]);

            return $resourceCollection;
        } catch (Exception $e) {
            Log::error('Error fetching products', [
                'exception' => $e->getMessage(),
                'merchant_id' => $merchantId,
            ]);

            return response()->json([
                'error' => 'Failed to fetch products',
                'message' => 'An error occurred while fetching the product list',
            ], 500);
        }
    }
}
