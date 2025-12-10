<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Exceptions\AnswerException;
use App\Integrations\Survey\Models\Answer;
use Illuminate\Support\Facades\Auth;

class CreateAnswerAction
{
    /** @param array<string, mixed> $data */
    public function execute(Answer $answer, array $data): Answer
    {
        $isAlreadyAnswered = Answer::query()
            ->where('submission_id', $data['submission_id'])
            ->where('question_id', $data['question_id'])
            ->exists();

        if ($isAlreadyAnswered) {
            throw AnswerException::alreadyAnswered((string) $data['question_id'], (string) $data['submission_id']);
        }

        $answer->fill($data);
        $answer->user_id = (string) Auth::user()?->id;
        $answer->save();

        return $answer->refresh();
    }
}
