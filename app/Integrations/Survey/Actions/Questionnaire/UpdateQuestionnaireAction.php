<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Models\Questionnaire;

class UpdateQuestionnaireAction
{
    /** @param array<string, mixed> $data */
    public function execute(Questionnaire $questionnaire, array $data): Questionnaire
    {
        $questionnaire->fill($data);
        $questionnaire->save();

        return $questionnaire->refresh();
    }
}
