<?php

namespace App\Http\Controllers\V1\Apideck;

use App\Actions\Apideck\SyncAllEmployeesAction;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserIndexResource;
use App\Services\Apideck\ApideckService;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

#[Group('Apideck')]
class SIRHEmployeeController extends Controller
{
    public function __construct(protected ApideckService $apideckService) {}

    /**
     * Sync employees from Apideck
     *
     * @return JsonResponse
     */
    public function sync(Request $request)
    {
        $validatedParams = $request->validate([
            'financer_id' => ['required', 'string', 'exists:financers,id'],
        ]);

        // Add user ID to params
        $validatedParams['user_id'] = auth()->id() ?? 'system';

        // Create the action to get sync ID
        $action = new SyncAllEmployeesAction($validatedParams);
        $syncId = $action->getSyncId();

        // Dispatch the sync job to queue for asynchronous processing
        SyncAllEmployeesAction::dispatch($validatedParams);

        return response()->json([
            'data' => [
                'message' => 'Employee sync job has been queued for batch processing',
                'status' => 'queued',
                'sync_id' => $syncId,
            ],
        ], 202);
    }

    /**
     * Fetch employees from Apideck
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "id": null,
     *       "email": "rsaito@efficientoffice.com",
     *       "email_verified_at": null,
     *       "first_name": "Ryota",
     *       "last_name": "Saito",
     *       "description": null,
     *       "birthdate": "1970-04-24T23:00:00.000000Z",
     *       "locale": "fr-FR",
     *       "currency": "EUR",
     *       "timezone": "Europe/Paris",
     *       "phone": "801-724-6600",
     *       "enabled": true,
     *       "profile_image": "",
     *       "financers": [],
     *       "roles": [],
     *       "permissions": [],
     *       "created_at": null,
     *       "updated_at": null
     *     }
     *   ],
     *   "meta": {
     *     "total": 5,
     *     "current_page": 1,
     *     "per_page": 5,
     *     "countries": {},
     *     "currencies": {},
     *     "languages": {},
     *     "timezones": {},
     *     "divisions": [],
     *     "financers": [],
     *     "roles": [],
     *     "total_items": 5,
     *     "cursors": {
     *       "previous": null,
     *       "current": null,
     *       "next": "MTAz"
     *     }
     *   }
     * }
     *
     * @return |array<string, mixed>
     */
    #[QueryParameter('per_page', description: 'Number of items per page.', type: 'integer', example: '20')]
    #[QueryParameter('page', description: 'Page number (cursor).', type: 'string', example: '1')]
    #[QueryParameter('financer_id', description: 'UUID of the financer.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('raw', description: 'Return raw response from Apideck.', type: 'boolean', example: 'false')]
    #[QueryParameter('unsynchronized', description: 'Filter only unsynchronized employees.', type: 'boolean', example: 'false')]
    #[QueryParameter('sort.by', description: 'Sort field.', type: 'string', example: 'first_name')]
    #[QueryParameter('sort.direction', description: 'Sort direction.', type: 'string', example: 'asc')]
    public function index(Request $request): AnonymousResourceCollection|array
    {
        $validatedParams = $request->validate($this->getRules());

        $perPageParam = $request->per_page;
        $pageParam = $request->page;

        // Ensure proper type handling before casting
        $perPage = is_numeric($perPageParam) && (int) $perPageParam !== 0 ? (int) $perPageParam : 20;
        $page = is_numeric($pageParam) && (int) $pageParam !== 0 ? (int) $pageParam : 1;

        $apiResponse = $this->apideckService->index($validatedParams, $perPage, $page);

        if (array_key_exists('error', $apiResponse)) {
            return $apiResponse;
        }

        return UserIndexResource::collection($apiResponse['employees'])->additional([
            'meta' => $apiResponse['meta'],
            'links' => $apiResponse['links'] ?? [],
        ]);
    }

    /**
     * Get validation rules
     *
     * @return array<string, array<int, mixed>>
     */
    protected function getRules(): array
    {
        return [
            'financer_id' => ['sometimes', 'string', 'exists:financers,id'],
            'raw' => ['nullable', 'boolean'],
            'unsynchronized' => ['nullable', 'boolean'],
            // Pagination
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'string'],
            // Sorting
            'sort.by' => [
                'nullable',
                Rule::in(['first_name', 'last_name', 'created_at', 'updated_at']), // Sortable fields
            ],
            'sort.direction' => [
                'nullable',
                Rule::in(['asc', 'desc']), // Sorting direction
            ],
        ];
    }
}
