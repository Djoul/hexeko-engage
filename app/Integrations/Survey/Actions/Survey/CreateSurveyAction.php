<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Jobs\SyncSurveyUsersJob;
use App\Integrations\Survey\Models\Survey;
use Illuminate\Support\Facades\DB;

class CreateSurveyAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Survey
    {
        return DB::transaction(function () use ($data) {
            $survey = new Survey;

            $survey->fill($data);

            $survey->save();

            SyncSurveyUsersJob::dispatch($survey);

            return $survey->refresh();
        });
    }
}
