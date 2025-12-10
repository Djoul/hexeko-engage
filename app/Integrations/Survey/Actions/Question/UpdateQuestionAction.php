<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Question;

use App\Integrations\Survey\Exceptions\QuestionException;
use App\Integrations\Survey\Models\Question;
use Illuminate\Support\Facades\DB;

class UpdateQuestionAction
{
    /** @param array<string, mixed> $data */
    public function execute(Question $question, array $data): Question
    {
        if (! $question->canBeModified()) {
            throw QuestionException::cannotModify($question->survey()?->getStatus() ?? '');
        }

        if ($question->answers()->exists()) {
            throw QuestionException::hasAnswers();
        }

        return DB::transaction(function () use ($question, $data) {
            $questionnaireId = $data['questionnaire_id'] ?? null;
            $surveyId = $data['survey_id'] ?? null;
            $options = $data['options'] ?? null;
            unset($data['questionnaire_id'], $data['survey_id'], $data['options']);

            $question->fill($data);
            $question->save();

            if ($questionnaireId !== null) {
                $question->questionnaires()->attach($questionnaireId);
            }

            if ($surveyId !== null) {
                // A question can only be linked to one survey, so we use sync instead of attach
                $question->surveys()->sync([$surveyId]);
            }

            $question->options()->delete();
            if ($options !== null && is_array($options)) {
                /** @var iterable<array<string, mixed>> $options */
                $question->options()->createMany($options);
            }

            return $question->refresh();
        });
    }
}
