<?php

namespace App\QueryFilters;

use App\QueryFilters\Contracts\Filter;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class AbstractFilter implements Filter
{
    final protected function filterName(): string
    {
        // ex: ApiEndpointFilter → api_endpoint
        $toString = str()
            ->of(class_basename($this))
            ->replaceLast('Filter', '')
            ->snake()
            ->toString();

        return $toString;
    }

    final protected function getParam(): ?string
    {
        $param = request()->query($this->filterName());

        if (is_array($param)) {
            return null;
        }

        return $param;
    }

    /**
     * Indicate whether this filter supports array parameters.
     * Override this method in child classes to enable array support.
     */
    protected function supportsArrayParams(): bool
    {
        return false;
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    abstract protected function applyFilter(Builder $query, mixed $value): Builder;

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  Closure(Builder<TModel>): mixed  $next
     */
    final public function handle(Builder $query, Closure $next): mixed
    {
        $rawParam = request()->query($this->filterName());
        $param = $this->getParam();

        // Special handling for filters that need to support arrays
        if (is_array($rawParam) && $this->supportsArrayParams()) {
            return $next($this->applyFilter($query, $rawParam));
        }

        if (is_null($param) && $this->filterName() !== 'financer_id') {
            return $next($query);
        }

        return $next($this->applyFilter($query, $param));
    }
}
