<?php

namespace App\Integrations\Survey\Actions\Me\Answer;

use App\Integrations\Survey\Models\Answer;

class DeleteAnswerAction
{
    public function execute(Answer $answer): ?bool
    {
        $answer->delete();

        return $answer->delete();
    }
}
