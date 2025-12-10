<?php

declare(strict_types=1);

namespace App\Actions\WorkMode;

use App\Models\WorkMode;

class UpdateWorkModeAction
{
    public function execute(WorkMode $workMode, array $data): WorkMode
    {
        $workMode->fill($data);
        $workMode->save();

        return $workMode->refresh();
    }
}
