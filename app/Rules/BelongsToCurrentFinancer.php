<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class BelongsToCurrentFinancer implements Rule
{
    public function __construct(
        private string $table,
        private ?string $foreignKey = 'financer_id',
        private ?string $pivotTable = null,
        private ?string $pivotForeignKey = null
    ) {}

    public function passes($attribute, $value): bool
    {
        if ($value === null) {
            return true; // Allow null values, let other rules handle required validation
        }

        $financerId = activeFinancerID();

        if (in_array($financerId, ['', '0', [], null], true)) {
            return false; // No financer context means validation fails
        }

        // If pivot table is specified, check through the pivot
        if ($this->pivotTable !== null) {
            $pivotForeignKey = $this->pivotForeignKey ?? 'financer_id';

            return DB::table($this->table)
                ->join($this->pivotTable, "{$this->table}.id", '=', "{$this->pivotTable}.{$this->foreignKey}")
                ->where("{$this->table}.id", $value)
                ->where("{$this->pivotTable}.{$pivotForeignKey}", $financerId)
                ->exists();
        }

        // Otherwise, check direct foreign key
        return DB::table($this->table)
            ->where('id', $value)
            ->where($this->foreignKey, $financerId)
            ->exists();
    }

    public function message(): string
    {
        return 'The selected :attribute does not belong to the current financer.';
    }
}
