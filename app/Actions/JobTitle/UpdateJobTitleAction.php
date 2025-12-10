<?php

declare(strict_types=1);

namespace App\Actions\JobTitle;

use App\Models\JobTitle;

class UpdateJobTitleAction
{
    public function execute(JobTitle $jobTitle, array $data): JobTitle
    {
        $jobTitle->fill($data);
        $jobTitle->save();

        return $jobTitle->refresh();
    }
}
