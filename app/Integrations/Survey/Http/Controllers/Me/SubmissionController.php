<?php

namespace App\Integrations\Survey\Http\Controllers\Me;

use App\Enums\Pagination;
use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Me\Submissions\CompleteSubmissionAction;
use App\Integrations\Survey\Actions\Me\Submissions\CreateSubmissionAction;
use App\Integrations\Survey\Actions\Me\Submissions\DeleteSubmissionAction;
use App\Integrations\Survey\Actions\Me\Submissions\UpdateSubmissionAction;
use App\Integrations\Survey\Http\Requests\Me\Submission\CreateSubmissionRequest;
use App\Integrations\Survey\Http\Requests\Me\Submission\IndexSubmissionRequest;
use App\Integrations\Survey\Http\Requests\Me\Submission\UpdateSubmissionRequest;
use App\Integrations\Survey\Http\Resources\SubmissionResource;
use App\Integrations\Survey\Models\Submission;
use App\Integrations\Survey\Pipelines\FilterPipelines\SubmissionPipeline;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Me/Modules/Survey/Submissions
 *
 * @authenticated
 */
#[Group('Me/Modules/Survey/Submissions')]
class SubmissionController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Submission::class, 'submission');
    }

    /**
     * List submissions
     *
     * Return a list of submissions with optional filters.
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('survey_id', description: 'Filter by survey ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    #[QueryParameter('created_at', description: 'Filter by creation date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('updated_at', description: 'Filter by update date.', type: 'date', example: '2024-01-01')]
    #[QueryParameter('deleted_at', description: 'Filter by deletion date (for soft deleted items).', type: 'date', example: '2024-01-01')]
    #[QueryParameter('per_page', description: 'Number of items per page (1-100).', type: 'integer', example: '20')]
    // Modular sorting
    #[QueryParameter('order-by', description: 'Ascending sort field default position.', type: 'string', example: 'position')]
    #[QueryParameter('order-by-desc', description: 'Descending sort field default created_at.', type: 'string', example: 'created_at')]
    public function index(IndexSubmissionRequest $request): AnonymousResourceCollection
    {
        /** @var int|null $requestedPerPage */
        $requestedPerPage = $request->validated('per_page');
        $perPage = $requestedPerPage ?? Pagination::MEDIUM;

        $user = Auth::user();
        if (! $user) {
            abort(401, 'Unauthenticated');
        }

        $submissions = Submission::query()
            ->with(['survey'])
            ->where('user_id', (string) $user->id)
            ->pipe(function ($query) {
                return resolve(SubmissionPipeline::class)->apply($query);
            })
            ->paginate($perPage);

        return SubmissionResource::collection($submissions);
    }

    /**
     * Create submission
     */
    public function store(CreateSubmissionRequest $request, CreateSubmissionAction $createSubmissionAction): SubmissionResource
    {
        $submission = $createSubmissionAction->execute(new Submission, $request->validated());

        return new SubmissionResource($submission->load(['survey']));
    }

    /**
     * Show submission
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Submission $submission): SubmissionResource
    {
        return new SubmissionResource($submission->load(['survey', 'answers']));
    }

    /**
     * Update submission
     */
    public function update(UpdateSubmissionRequest $request, Submission $submission, UpdateSubmissionAction $updateSubmissionAction): SubmissionResource
    {
        $submission = $updateSubmissionAction->execute($submission, $request->validated());

        return new SubmissionResource($submission->load(['survey', 'answers']));
    }

    /**
     * Delete submission
     */
    public function destroy(Submission $submission, DeleteSubmissionAction $deleteSubmissionAction): Response
    {
        $deleteSubmissionAction->execute($submission);

        return response()->noContent();
    }

    /**
     * Complete submission
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function complete(Submission $submission, CompleteSubmissionAction $completeSubmissionAction): SubmissionResource
    {
        Gate::authorize('complete', $submission);

        return new SubmissionResource($completeSubmissionAction->execute($submission));
    }
}
