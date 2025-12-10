<?php

namespace App\Integrations\Survey\Http\Controllers;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Http\Resources\User\UserResource;
use App\Integrations\Survey\Http\Requests\Survey\IndexSurveyUserRequest;
use App\Integrations\Survey\Models\Survey;
use App\Integrations\Survey\Pipelines\FilterPipelines\SurveyUserPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

/**
 * @group Modules/Survey/Surveys/Users
 *
 * @authenticated
 */
#[Group('Modules/Survey/Surveys/Users')]
class SurveyUserController extends Controller
{
    /**
     * List users
     *
     * Return a list of users with optional filters.
     */
    #[QueryParameter('page', description: 'Page number.', type: 'integer', example: '1')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    #[QueryParameter('order-by', description: 'Ascending sort field default last name.', type: 'string', example: 'last_name')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field.', type: 'string', example: 'created_at')]
    public function index(IndexSurveyUserRequest $request, Survey $survey): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Survey::class);

        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $users = $survey->users()
            ->pipe(function ($query) {
                return resolve(SurveyUserPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return UserResource::collection($users);
    }
}
