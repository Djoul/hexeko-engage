<?php

namespace App\Integrations\Survey\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Integrations\Survey\Actions\Me\Answer\CreateAnswerAction;
use App\Integrations\Survey\Actions\Me\Answer\DeleteAnswerAction;
use App\Integrations\Survey\Actions\Me\Answer\UpdateAnswerAction;
use App\Integrations\Survey\Http\Requests\Me\Answer\CreateAnswerRequest;
use App\Integrations\Survey\Http\Requests\Me\Answer\UpdateAnswerRequest;
use App\Integrations\Survey\Http\Resources\AnswerResource;
use App\Integrations\Survey\Models\Answer;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Symfony\Component\HttpFoundation\Response;

/**
 * @group Modules/Survey/Answers
 *
 * @authenticated
 */
#[Group('Me/Modules/Survey/Answers')]
class AnswerController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Answer::class, 'answer');
    }

    /**
     * Create answer
     *
     * Expected request payload varies with the question type:
     * - `text`: send the free-form text in `answer.value`, e.g. `{ "answer": { "value": "My feedback" } }`
     * - `scale`: send the selected scale point as a string or integer in `answer.value`, e.g. `{ "answer": { "value": "4" } }`
     * - `single_choice`: send the selected option UUID as `answer.value`, e.g. `{ "answer": { "value": "option-uuid" } }`
     * - `multiple_choice`: send an array of option UUIDs in `answer.value`, e.g. `{ "answer": { "value": ["option-uuid-1", "option-uuid-2"] } }`
     *
     * Shared fields `submission_id` and `question_id` must always be present alongside `answer`.
     */
    public function store(CreateAnswerRequest $request, CreateAnswerAction $updateAnswerAction): AnswerResource
    {
        $question = $updateAnswerAction->execute(new Answer, $request->validated());

        return new AnswerResource($question->load(['submission', 'question']));
    }

    /**
     * Show answer
     */
    #[QueryParameter('financer_id', description: 'Filter by financer ID.', type: 'uuid', example: '123e4567-e89b-12d3-a456-426614174000')]
    public function show(Answer $answer): AnswerResource
    {
        return new AnswerResource($answer->load(['submission', 'question']));
    }

    /**
     * Update answer
     *
     * Expected request payload varies with the question type:
     * - `text`: send the free-form text in `answer.value`, e.g. `{ "answer": { "value": "My feedback" } }`
     * - `scale`: send the selected scale point as a string or integer in `answer.value`, e.g. `{ "answer": { "value": "4" } }`
     * - `single_choice`: send the selected option UUID as `answer.value`, e.g. `{ "answer": { "value": "option-uuid" } }`
     * - `multiple_choice`: send an array of option UUIDs in `answer.value`, e.g. `{ "answer": { "value": ["option-uuid-1", "option-uuid-2"] } }`
     */
    public function update(UpdateAnswerRequest $request, Answer $answer, UpdateAnswerAction $updateAnswerAction): AnswerResource
    {
        $answer = $updateAnswerAction->execute($answer, $request->validated());

        return new AnswerResource($answer->load(['submission', 'question']));
    }

    /**
     * Delete answer
     */
    public function destroy(Answer $answer, DeleteAnswerAction $deleteAnswerAction): Response
    {
        return response()->json(['success' => $deleteAnswerAction->execute($answer)])->setStatusCode(204);
    }
}
