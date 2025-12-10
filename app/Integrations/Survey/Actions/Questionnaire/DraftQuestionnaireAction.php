<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Questionnaire;

use App\Integrations\Survey\Enums\QuestionnaireStatusEnum;
use App\Integrations\Survey\Models\Questionnaire;

class DraftQuestionnaireAction
{
    /** @param array<string, mixed> $data */
    public function execute(Questionnaire $questionnaire, array $data): Questionnaire
    {
        $data['status'] = QuestionnaireStatusEnum::DRAFT;

        $questionnaire->fill($data);

        $questionnaire->save();

        return $questionnaire->refresh();
    }
}
