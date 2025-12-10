<?php

declare(strict_types=1);

namespace App\Actions\Department;

use App\Models\Department;

class UpdateDepartmentAction
{
    /** @param array<string, mixed> $data */
    public function execute(Department $department, array $data): Department
    {
        $department->fill($data);
        $department->save();

        return $department->refresh();
    }
}
