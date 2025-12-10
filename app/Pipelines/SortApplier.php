<?php

namespace App\Pipelines;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Applies modular sorting to an Eloquent query.
 */
class SortApplier
{
    /**
     * Get the current locale based on the fallback logic.
     */
    protected static function getCurrentLocale(): string
    {

        // First try to get from authenticated user
        if (Auth::check()) {
            /** @var User $user */
            $user = Auth::user();
            if (! empty($user->locale) && is_string($user->locale)) {
                return $user->locale;
            }
        }

        // Then try to get from active financer
        $financerId = activeFinancerID();

        // Skip financer lookup if no financer ID is available
        $financer = in_array($financerId, ['', '0', []], true) ? null : Financer::find($financerId);

        if ($financer) {
            // Check if financer has available languages
            if (! empty($financer->available_languages) && is_array($financer->available_languages) && array_key_exists(0, $financer->available_languages)) {
                $language = $financer->available_languages[0];

                return is_scalar($language) ? (string) $language : 'en';
            }

            // Finally check division language
            if ($financer->division && ! empty($financer->division->language) && is_string($financer->division->language)) {
                return $financer->division->language;
            }
        }

        // Default fallback
        $fallbackLocale = config('app.fallback_locale');

        return is_string($fallbackLocale) ? $fallbackLocale : 'en';
    }

    /**
     * Applies modular sorting to an Eloquent query.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string>  $sortable  List of sortable regular columns
     * @param  array<string>  $pivotSortable  List of sortable pivot columns (optional)
     * @return Builder<TModel>
     */
    public static function apply(
        Builder $query,
        array $sortable,
        string $defaultField,
        string $defaultDirection,
        array $pivotSortable = []
    ): Builder {
        // Reset any previous sorting (important for consistency)
        $query->getQuery()->orders = null;

        // Get sort parameters from request
        $orderByDesc = request('order-by-desc');
        $orderBy = request('order-by');

        // Get the model instance to check for field mappings
        $model = $query->getModel();
        $modelClass = get_class($model);

        // Check if model has field mapping
        $fieldMap = [];
        if (method_exists($modelClass, 'getSortableFieldMap')) {
            $fieldMap = $modelClass::getSortableFieldMap();
        }

        // Default to using the default sort parameters
        $field = $defaultField;
        $direction = $defaultDirection;

        // Check for order-by-desc parameter (takes precedence)
        if (request()->has('order-by-desc') && is_string($orderByDesc)) {
            $mappedField = $fieldMap[$orderByDesc] ?? $orderByDesc;
            if (in_array($mappedField, $sortable, true)) {
                $field = $mappedField;
                $direction = 'desc';
            } else {
                // Invalid field provided - throw error
                abort(422, "Invalid sort field: {$orderByDesc}");
            }
        }
        // If no order-by-desc, check for order-by parameter
        elseif (request()->has('order-by') && is_string($orderBy)) {
            $mappedField = $fieldMap[$orderBy] ?? $orderBy;
            if (in_array($mappedField, $sortable, true)) {
                $field = $mappedField;
                $direction = 'asc';
            } else {
                // Invalid field provided - throw error
                abort(422, "Invalid sort field: {$orderBy}");
            }
        }
        // If neither order-by-desc nor order-by are valid, use the default sort parameters
        else {
            $field = $defaultField;
            $direction = $defaultDirection;
        }

        // Check if this is a pivot column (for many-to-many relationships)
        if (in_array($field, $pivotSortable, true)) {
            // Use orderByPivot for pivot columns
            return self::applyPivotSort($query, $field, $direction);
        }

        // Check if model has custom sortable expression
        $customExpression = null;
        if (method_exists($modelClass, 'getSortableExpression')) {
            $customExpression = $modelClass::getSortableExpression($field);
        }

        // If custom expression exists, use it
        if ($customExpression !== null) {
            $directionSql = $direction === 'desc' ? 'DESC' : 'ASC';
            $query->orderByRaw("{$customExpression} {$directionSql}");

            return $query;
        }

        // Check if the field is translatable
        $isTranslatable = false;
        if (property_exists($model, 'translatable') && is_array($model->translatable)) {
            $isTranslatable = in_array($field, $model->translatable, true);
        }

        // Apply the sort
        if (strpos($field, '.') !== false) {
            [$relation, $column] = explode('.', $field, 2);
            // add a calculated column containing an aggregated value to the Eloquent query example: translations_title
            $query->withAggregate($relation, $column);
            $query->orderBy($relation.'_'.$column, $direction);
        } elseif ($isTranslatable) {
            // For translatable fields, use orderByRaw with the current locale
            $locale = self::getCurrentLocale();
            $directionSql = $direction === 'desc' ? 'DESC' : 'ASC';
            $query->orderByRaw("{$field}->>'$locale' {$directionSql}");
        } else {
            // Apply the sort
            $query->orderBy($field, $direction);
        }

        return $query;
    }

    /**
     * Apply sorting on pivot columns for many-to-many relationships.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected static function applyPivotSort(
        Builder $query,
        string $field,
        string $direction
    ): Builder {
        // Check if the query supports orderByPivot (BelongsToMany/MorphToMany)
        if (method_exists($query, 'orderByPivot')) {
            $query->orderByPivot($field, $direction);
        } else {
            // Fallback: Use the aliased pivot column name
            // Laravel aliases pivot columns as "pivot_{column_name}" when selecting
            $query->orderBy("pivot_{$field}", $direction);
        }

        return $query;
    }
}
