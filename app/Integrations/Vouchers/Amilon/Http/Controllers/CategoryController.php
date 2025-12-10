<?php

namespace App\Integrations\Vouchers\Amilon\Http\Controllers;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Integrations\Vouchers\Amilon\Http\Resources\CategoryResourceCollection;
use App\Integrations\Vouchers\Amilon\Services\AmilonCategoryService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

#[Group('Modules/Vouchers/Amilon')]
class CategoryController extends Controller
{
    /**
     * Create a new controller instance.
     */
    public function __construct(
        protected AmilonCategoryService $amilonCategoryService
    ) {}

    /**
     * Get all categories.
     */
    #[RequiresPermission(PermissionDefaults::CREATE_VOUCHER)]
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '15')]
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    public function index(Request $request): JsonResponse|CategoryResourceCollection
    {
        try {
            $perPageParam = $request->per_page;
            $pageParam = $request->page;
            // Ensure proper type handling before casting
            $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
            $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

            $categories = $this->amilonCategoryService->getCategories();
            $categoryCollection = collect($categories);
            $total = $categoryCollection->count();

            // Apply pagination if requested
            if ($request->has('page') || $request->has('per_page')) {
                $categoryCollection = $categoryCollection->forPage($page, $perPage);
            }

            $collection = new CategoryResourceCollection($categoryCollection);

            return $collection->additional([
                'meta' => ['total_items' => $total],
            ]);

        } catch (Exception $e) {
            Log::error('Error fetching categories', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to fetch categories',
                'message' => 'An error occurred while fetching the category list',
            ], 500);
        }
    }
}
