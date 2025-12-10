<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Models\Answer;

class UpdateAnswerAction
{
    /** @param array<string, mixed> $data */
    public function execute(Answer $answer, array $data): Answer
    {
        $answer->fill($data);

        $answer->save();

        return $answer->refresh();
    }
}
