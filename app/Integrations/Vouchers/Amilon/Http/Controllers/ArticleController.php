<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Http\Resources\ProductResourceCollection;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Dedoc\Scramble\Attributes\QueryParameter;
use Deprecated;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

#[ExcludeRouteFromDocs]
class ArticleController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonProductService $amilonService
    ) {}

    /**
     * Get all products for a specific merchant.
     */
    #[ExcludeRouteFromDocs]
    #[Deprecated('This controller is deprecated and will be removed in the next major version. Use ProductController instead.')]
    #[RequiresPermission(PermissionDefaults::UPDATE_HRTOOLS)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    public function index(Request $request, string $merchantId): JsonResponse|ProductResourceCollection
    {
        try {
            // Verify that the merchant exists
            $merchant = Merchant::byMerchantId($merchantId)->first();

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

            $products = $this->amilonService->getProducts();
            /** @var Collection<int, mixed> $collection */
            $collection = collect($products);

            // Apply pagination if requested
            if ($request->has('page') || $request->has('per_page')) {
                $collection = $collection->forPage($page, $perPage);
            }

            return new ProductResourceCollection($collection);
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
