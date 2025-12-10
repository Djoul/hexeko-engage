<?php

declare(strict_types=1);

namespace App\Actions\WorkMode;

use App\Models\WorkMode;

class CreateWorkModeAction
{
    public function execute(array $data): WorkMode
    {
        $workMode = new WorkMode;
        $workMode->fill($data);
        $workMode->save();

        return $workMode->refresh();
    }
}
