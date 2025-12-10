<?php

namespace App\Pipelines\FilterPipelines;

use App\Models\User;
use App\Pipelines\SortApplier;
use App\QueryFilters\ModelSpecific\User\DivisionIdFilter;
use App\QueryFilters\ModelSpecific\User\EmailFilter;
use App\QueryFilters\ModelSpecific\User\EnabledFilter;
use App\QueryFilters\ModelSpecific\User\FinancerIdFilter;
use App\QueryFilters\ModelSpecific\User\FirstNameFilter;
use App\QueryFilters\ModelSpecific\User\GlobalSearchFilter;
use App\QueryFilters\ModelSpecific\User\IsAdminFilter;
use App\QueryFilters\ModelSpecific\User\LastNameFilter;
use App\QueryFilters\ModelSpecific\User\PhoneFilter;
use App\QueryFilters\ModelSpecific\User\RoleFilter;
use App\QueryFilters\ModelSpecific\User\StatusFilter;
use App\QueryFilters\ModelSpecific\User\TeamIdFilter;
use App\QueryFilters\Shared\CountryFilter;
use App\QueryFilters\Shared\CurrencyFilter;
use App\QueryFilters\Shared\DateFromFilter;
use App\QueryFilters\Shared\DateToFilter;
use App\QueryFilters\Shared\IdFilter;
use App\QueryFilters\Shared\LanguageFilter;
use App\QueryFilters\Shared\TimezoneFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

class UserPipeline
{
    /**
     * @var array<int, class-string>
     */
    protected array $filters = [
        // Global search filter - must be first
        GlobalSearchFilter::class,

        // Shared generic filters
        IdFilter::class,
        DateFromFilter::class,
        DateToFilter::class,
        CountryFilter::class,
        CurrencyFilter::class,
        LanguageFilter::class,
        TimezoneFilter::class,

        // User-specific filters*/
        DivisionIdFilter::class,
        FinancerIdFilter::class,

        EmailFilter::class,
        FirstNameFilter::class,
        LastNameFilter::class,
        PhoneFilter::class,
        EnabledFilter::class,
        TeamIdFilter::class,
        RoleFilter::class,
        IsAdminFilter::class,
        StatusFilter::class,
    ];

    /**
     * Apply filters to query
     *
     * @param  Builder<User>  $query
     * @param  bool  $applySorting  Whether to apply SQL-level sorting (default true)
     * @return Builder<User>
     */
    public function apply($query, bool $applySorting = true): Builder
    {
        /** @var Builder<User> $result */
        $result = app(Pipeline::class)
            ->send($query)
            ->through($this->filters)
            ->thenReturn();

        // Skip SQL sorting if requested (e.g., when controller handles PHP-level sorting)
        if (! $applySorting) {
            return $result;
        }

        $modelClass = get_class($result->getModel());
        $sortable = $modelClass::$sortable ?? [];
        $defaultField = $modelClass::$defaultSortField ?? 'created_at';
        $defaultDirection = $modelClass::$defaultSortDirection ?? 'desc';

        return SortApplier::apply($result, $sortable, $defaultField, $defaultDirection);
    }
}
