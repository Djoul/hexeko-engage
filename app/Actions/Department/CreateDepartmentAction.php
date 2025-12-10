<?php

declare(strict_types=1);

namespace App\Actions\Department;

use App\Models\Department;

class CreateDepartmentAction
{
    /** @param array<string, mixed> $data */
    public function execute(array $data): Department
    {
        $department = new Department;
        $department->fill($data);
        $department->save();

        return $department->refresh();
    }
}
