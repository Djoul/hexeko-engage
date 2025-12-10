<?php

declare(strict_types=1);

namespace App\Actions\JobLevel;

use App\Models\JobLevel;

class CreateJobLevelAction
{
    public function execute(array $data): JobLevel
    {
        $jobLevel = new JobLevel;
        $jobLevel->fill($data);
        $jobLevel->save();

        return $jobLevel->refresh();
    }
}
