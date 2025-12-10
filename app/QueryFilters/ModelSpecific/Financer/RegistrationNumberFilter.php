<?php

namespace App\QueryFilters\ModelSpecific\Financer;

use App\QueryFilters\Shared\TextFilter;

class RegistrationNumberFilter extends TextFilter
{
    /**
     * Override to use 'registration_number' column instead of 'registration_number_filter' column.
     */
    protected function getColumnName(): string
    {
        return 'registration_number';
    }
}
