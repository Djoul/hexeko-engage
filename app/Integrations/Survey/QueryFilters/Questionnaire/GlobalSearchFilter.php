<?php

declare(strict_types=1);

namespace App\Integrations\Survey\QueryFilters\Questionnaire;

use App\Integrations\Survey\Models\Questionnaire;
use App\QueryFilters\Contracts\Filter;
use App\Traits\SearchableFilter;
use Illuminate\Database\Eloquent\Model;

class GlobalSearchFilter implements Filter
{
    use SearchableFilter;

    /**
     * Get the model instance.
     */
    protected function getModel(): Model
    {
        return new Questionnaire;
    }
}
