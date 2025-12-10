<?php

declare(strict_types=1);

namespace App\Actions\JobLevel;

use App\Models\JobLevel;

class UpdateJobLevelAction
{
    public function execute(JobLevel $jobLevel, array $data): JobLevel
    {
        $jobLevel->fill($data);
        $jobLevel->save();

        return $jobLevel->refresh();
    }
}
