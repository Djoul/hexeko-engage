<?php

declare(strict_types=1);

namespace App\QueryFilters\ModelSpecific\User;

use App\Models\User;
use App\Traits\SearchableFilter;
use Illuminate\Database\Eloquent\Model;

class GlobalSearchFilter
{
    use SearchableFilter;

    protected function getModel(): Model
    {
        return new User;
    }
}
