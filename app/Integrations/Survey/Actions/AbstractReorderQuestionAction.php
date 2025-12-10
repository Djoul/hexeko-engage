<?php

namespace App\Integrations\Survey\Actions;

use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Models\Survey;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

abstract class AbstractReorderQuestionAction
{
    /**
     * Reorder questions in a questionable entity (survey, questionnaire, etc.).
     *
     * @param  array<int, array{id: string, position: int}>  $questions
     */
    final protected function reorderQuestions(Survey|Questionnaire $questionable, array $questions): Model
    {
        if ($questions === []) {
            return $questionable->refresh();
        }

        $questionIds = array_column($questions, 'id');

        $linkedQuestionIds = $questionable->questions()->pluck('question_id')->toArray();

        $unlinkedQuestionIds = array_diff($questionIds, $linkedQuestionIds);

        if ($unlinkedQuestionIds !== []) {
            $entityType = $questionable instanceof Survey ? 'survey' : 'questionnaire';
            throw new InvalidArgumentException(
                sprintf(
                    'The following questions are not linked to this %s: %s',
                    $entityType,
                    implode(', ', $unlinkedQuestionIds)
                )
            );
        }

        $syncData = [];

        foreach ($questions as $question) {
            $syncData[$question['id']] = ['position' => $question['position']];
        }

        $questionable->questions()->syncWithoutDetaching($syncData);

        return $questionable->refresh();
    }
}
