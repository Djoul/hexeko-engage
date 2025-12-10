<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class DraftSurveyAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Survey
    {
        return DB::transaction(function () use ($data) {
            $survey = new Survey;

            $data['status'] = SurveyStatusEnum::DRAFT;

            $survey->fill($data);

            $survey->save();

            return $survey->refresh();
        });

    }
}
