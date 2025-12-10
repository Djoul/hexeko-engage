<?php

namespace App\Integrations\Survey\Http\Requests\Me\Answer;

use App\Integrations\Survey\Enums\QuestionTypeEnum;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Submission;
use Illuminate\Foundation\Http\FormRequest;

/**
 * @method array<string, mixed> all()
 */
class CreateAnswerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        $data = $this->all();

        /** @var string|null $submissionId */
        $submissionId = $data['submission_id'] ?? null;
        $submission = Submission::query()->where('id', $submissionId)->firstOrFail();
        $questionIds = $submission->survey->questions->pluck('id')->toArray();

        /** @var string|null $questionId */
        $questionId = $data['question_id'] ?? null;
        $question = Question::query()->where('id', $questionId)->firstOrFail();
        $type = $question->type;

        return [
            'submission_id' => 'required|uuid',
            'question_id' => 'required|uuid|in:'.implode(',', $questionIds),
            'answer' => 'required|array',
            'answer.value' => 'required|'.QuestionTypeEnum::type($type),
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'answer.type.in' => __('The answer type is invalid'),
            'answer.value.required' => __('The answer value is required'),
            'answer.value.array' => __('The answer value must be an array'),
            'answer.value.string' => __('The answer value must be a string'),
        ];
    }
}
