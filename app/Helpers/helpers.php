<?php

use App\Models\Financer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

if (! function_exists('loadRelationIfNotLoaded')) {
    /**
     * Charge une relation (ou plusieurs) même composée, si elle n'est pas déjà chargée.
     *
     * @param  string|array<int, string>  $relations
     */
    function loadRelationIfNotLoaded(Model $model, string|array $relations): void
    {
        foreach ((array) $relations as $relation) {
            loadNestedRelation($model, $relation);
        }
    }

    /**
     * Charge une relation composée (dot notation).
     *
     * @param  Model|Collection<int, Model>  $target
     */
    function loadNestedRelation(Model|Collection $target, string $relation): void
    {
        $segments = explode('.', $relation);
        $current = array_shift($segments);

        if ($target instanceof Collection) {
            foreach ($target as $item) {
                if ($item instanceof Model) {
                    loadNestedRelation($item, implode('.', array_merge([$current], $segments)));
                }
            }

            return;
        }

        if (! method_exists($target, $current)) {
            return;
        }

        // Load the relation if not already loaded
        if (! $target->relationLoaded($current)) {
            $target->load($current);
        }

        // Access the property (Model's __get magic method will handle property access)
        $next = $target->$current;

        if (count($segments) && $next) {
            loadNestedRelation($next, implode('.', $segments));
        }
    }
}

if (! function_exists('safeString')) {

    function safeString(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return '';
    }
}

if (! function_exists('paginated')) {
    function paginated(): bool
    {
        if (request()->has('page')) {
            return true;
        }

        return request()->has('per_page');
    }
}

if (! function_exists('financer')) {
    /**
     * Get the current financer.
     *
     * @param  string|null  $key
     * @return Financer|null|mixed
     */
    function financer($key = null)
    {
        if (! authorizationContext()->currentFinancerId()) {
            return;
        }

        if (is_null($key)) {
            return Financer::find(authorizationContext()->currentFinancerId());
        }

        return optional(Financer::find(authorizationContext()->currentFinancerId()))->getAttribute($key) ?? null;
    }
}
