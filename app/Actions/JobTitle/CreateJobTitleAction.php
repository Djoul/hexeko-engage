<?php

declare(strict_types=1);

namespace App\Actions\JobTitle;

use App\Models\JobTitle;

class CreateJobTitleAction
{
    public function execute(array $data): JobTitle
    {
        $jobTitle = new JobTitle;
        $jobTitle->fill($data);
        $jobTitle->save();

        return $jobTitle->refresh();
    }
}
