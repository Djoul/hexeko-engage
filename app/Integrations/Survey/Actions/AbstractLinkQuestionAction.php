<?php

namespace App\Integrations\Survey\Actions;

use App\Integrations\Survey\Exceptions\QuestionNotFoundException;
use App\Integrations\Survey\Models\Question;
use App\Integrations\Survey\Models\Questionnaire;
use App\Integrations\Survey\Models\Survey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

abstract class AbstractLinkQuestionAction
{
    /**
     * Link questions to a questionable entity (survey, questionnaire, etc.).
     *
     * When linking a question to a questionable entity, we need to check if the question
     * is already linked to the entity. If the question is not yet linked to
     * the entity, then we must duplicate the question first, then link it.
     * This ensures that each entity has its own copy of questions, allowing
     * for independent modifications without affecting other entities.
     *
     * @param  array<int|string, array{id: string, position?: int}|string>  $questions
     */
    final protected function linkQuestions(Survey|Questionnaire $questionable, array $questions): Model
    {
        if ($questions === []) {
            return $questionable->refresh();
        }

        return DB::transaction(function () use ($questionable, $questions) {
            /** @var array<string, array<string, int>> $syncData */
            $syncData = [];

            // Normalize questions to array format
            /** @var array<int, array{id: string, position?: int}> $normalizedQuestions */
            $normalizedQuestions = is_array($questions[0] ?? null)
                ? $questions
                : array_map(fn ($question): array => ['id' => $question], $questions);

            foreach ($normalizedQuestions as $questionData) {
                $questionId = $questionData['id'];
                $position = $questionData['position'] ?? null;

                // Check if the question is already linked to this entity
                $isAlreadyLinked = $questionable->questions()->where('question_id', $questionId)->exists();

                if ($isAlreadyLinked) {
                    // Question is already linked, use the existing question
                    $finalQuestionId = $questionId;
                } else {
                    // Question is not linked, duplicate it first
                    $questionModel = Question::query()->where('id', $questionId)->first();

                    if (! $questionModel) {
                        throw new QuestionNotFoundException($questionId);
                    }

                    $duplicatedQuestion = $questionModel->duplicate($questionable->financer_id);
                    $finalQuestionId = $duplicatedQuestion->id;
                }

                // Prepare sync data with position if provided
                $syncData[$finalQuestionId] = $position !== null ? ['position' => $position] : [];
            }

            $questionable->questions()->syncWithoutDetaching($syncData);

            return $questionable->refresh();
        });
    }
}
