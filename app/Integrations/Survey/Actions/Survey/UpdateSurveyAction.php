<?php

declare(strict_types=1);

namespace App\Integrations\Survey\Actions\Survey;

use App\Integrations\Survey\Enums\SurveyStatusEnum;
use App\Integrations\Survey\Exceptions\SurveyException;
use App\Integrations\Survey\Jobs\SyncSurveyUsersJob;
use App\Integrations\Survey\Models\Survey;

class UpdateSurveyAction
{
    /** @param array<string, mixed> $data */
    public function execute(Survey $survey, array $data): Survey
    {
        $refreshUsers = $data['refresh_users'] ?? false;
        unset($data['refresh_users']);

        if (! $survey->canBeModified()) {
            throw SurveyException::cannotModify($survey->getStatus());
        }

        if (array_key_exists('starts_at', $data) && array_key_exists('ends_at', $data) && $data['starts_at'] !== null && $data['ends_at'] !== null && $data['ends_at'] <= $data['starts_at']) {
            throw SurveyException::invalidDateRange();
        }

        if ($survey->status === SurveyStatusEnum::ARCHIVED) {
            $survey->archived_at = now();
        }

        $survey->fill($data);
        $survey->save();

        if ($refreshUsers === 'true' || $refreshUsers === true || $survey->status === SurveyStatusEnum::PUBLISHED) {
            SyncSurveyUsersJob::dispatch($survey);
        }

        return $survey->refresh();
    }
}
