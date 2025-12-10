<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class SegmentService
{
    /**
     * Get all available fields from configuration.
     */
    public function getAvailableFilters(): array
    {
        return config('segments.filters', []);
    }

    /**
     * Get all available operators from configuration.
     */
    public function getAvailableOperators(): array
    {
        return config('segments.operators', []);
    }

    /**
     * Apply filters to an Eloquent query builder.
     * This is a stateless helper method called by the Segment model.
     */
    public function applyFiltersToQuery(Builder $query, array $filters): Builder
    {
        if ($filters === []) {
            return $query;
        }

        // Group filters by condition to handle AND/OR priority.
        // AND has higher priority than OR.
        $groups = $this->groupFiltersByCondition($filters);

        // Apply each group.
        foreach ($groups as $index => $group) {
            if ($index === 0) {
                // First group: use where.
                $query->where(function (Builder $q) use ($group): void {
                    $this->applyFilterGroup($q, $group);
                });
            } else {
                // Next groups: use orWhere.
                $query->orWhere(function (Builder $q) use ($group): void {
                    $this->applyFilterGroup($q, $group);
                });
            }
        }

        return $query;
    }

    /**
     * Groups filters by separating them on OR conditions.
     * Example: [A AND B OR C AND D] becomes [[A AND B], [C AND D]]
     */
    protected function groupFiltersByCondition(array $filters): array
    {
        $groups = [];
        $currentGroup = [];

        foreach ($filters as $filter) {
            $currentGroup[] = $filter;

            // Close the group on OR, or if it's the last filter.
            if (($filter['condition'] ?? null) === 'OR') {
                $groups[] = $currentGroup;
                $currentGroup = [];
            }
        }

        // Add the last group if it is not empty.
        if ($currentGroup !== []) {
            $groups[] = $currentGroup;
        }

        return $groups;
    }

    /**
     * Applies a group of filters using AND.
     */
    protected function applyFilterGroup(Builder $query, array $filters): void
    {
        foreach ($filters as $filter) {
            $this->applyFilter($query, $filter);
        }
    }

    /**
     * Applies an individual filter
     */
    protected function applyFilter(Builder $query, array $filter): void
    {
        $type = $filter['type'];
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;

        // Retrieve the field config
        $allFilters = $this->getAvailableFilters();
        $filterConfig = $allFilters[$type] ?? null;

        if (! $filterConfig) {
            return; // Field not configured, ignore
        }

        // If it's a relation
        if (in_array($filterConfig['type'], ['relation', 'relation_field'])) {
            $this->applyRelationFilter($query, $filter, $filterConfig);
        } else {
            // Standard filter on a direct field
            $this->applyDirectFilter($query, $type, $operator, $value);
        }
    }

    /**
     * Applies a filter on a relation
     */
    protected function applyRelationFilter(Builder $query, array $filter, array $filterConfig): void
    {
        $operator = $filter['operator'];
        $value = $filter['value'] ?? null;
        $relationName = $filterConfig['relation_name'];
        $relatedField = $filterConfig['related_field'];

        match ($operator) {
            // Has at least one element in the relation
            'has' => $query->has($relationName),

            // Does not have any element in the relation
            'has_not' => $query->doesntHave($relationName),

            // Has an element where related_field is in the list
            'in' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->whereIn($relatedField, $this->parseArrayValue($value));
            }),

            // Does not have an element where related_field is in the list
            'not_in' => $query->whereDoesntHave($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->whereIn($relatedField, $this->parseArrayValue($value));
            }),

            // Filters on the relation fields
            'equals' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, '=', $value);
            }),

            'not_equals' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, '!=', $value);
            }),

            'contains' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, 'LIKE', "%{$value}%");
            }),

            'starts_with' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, 'LIKE', "{$value}%");
            }),

            'ends_with' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, 'LIKE', "%{$value}");
            }),

            // Number of elements in relation
            'count_equals' => $query->has($relationName, '=', $value),
            'count_more_than' => $query->has($relationName, '>', $value),
            'count_less_than' => $query->has($relationName, '<', $value),

            // Aggregations on relations
            'sum_more_than' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->havingRaw("SUM({$relatedField}) > ?", [$value]);
            }),

            'sum_less_than' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->havingRaw("SUM({$relatedField}) < ?", [$value]);
            }),

            'avg_more_than' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->havingRaw("AVG({$relatedField}) > ?", [$value]);
            }),

            'avg_less_than' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->havingRaw("AVG({$relatedField}) < ?", [$value]);
            }),

            'is_null' => $query->whereHas($relationName, function (Builder $q) use ($relatedField): void {
                $q->whereNull($relatedField);
            }),

            'is_not_null' => $query->whereHas($relationName, function (Builder $q) use ($relatedField): void {
                $q->whereNotNull($relatedField);
            }),

            'after' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, '>', $value);
            }),
            'before' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->where($relatedField, '<', $value);
            }),
            'between' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->whereBetween($relatedField, $value);
            }),
            'not_between' => $query->whereHas($relationName, function (Builder $q) use ($relatedField, $value): void {
                $q->whereNotBetween($relatedField, $value);
            }),

            default => null,
        };
    }

    /**
     * Applies a direct (non-relation) filter
     */
    protected function applyDirectFilter(Builder $query, string $field, string $operator, $value): void
    {
        match ($operator) {
            'equals', 'is' => $query->where($field, '=', $value),
            'not_equals', 'is_not' => $query->where($field, '!=', $value),
            'more_than', 'greater_than' => $query->where($field, '>', $value),
            'less_than' => $query->where($field, '<', $value),
            'more_than_or_equal', 'gte' => $query->where($field, '>=', $value),
            'less_than_or_equal', 'lte' => $query->where($field, '<=', $value),

            'between' => $query->whereBetween($field, $this->parseBetweenValue($value)),
            'not_between' => $query->whereNotBetween($field, $this->parseBetweenValue($value)),

            'in' => $query->whereIn($field, $this->parseArrayValue($value)),
            'not_in' => $query->whereNotIn($field, $this->parseArrayValue($value)),

            'contains' => $query->where($field, 'LIKE', "%{$value}%"),
            'starts_with' => $query->where($field, 'LIKE', "{$value}%"),
            'ends_with' => $query->where($field, 'LIKE', "%{$value}"),
            'is_null', 'empty' => $query->whereNull($field),
            'is_not_null', 'not_empty' => $query->whereNotNull($field),
            'before' => $query->whereDate($field, '<', $value),
            'after' => $query->whereDate($field, '>', $value),
            default => null,
        };
    }

    /**
     * Parse the value for between (accepts array or string)
     */
    protected function parseBetweenValue($value): array
    {
        if (is_array($value)) {
            if (isset($value['min']) && isset($value['max'])) {
                return [$value['min'], $value['max']];
            }

            return array_values($value);
        }

        if (is_string($value) && str_contains($value, ',')) {
            return explode(',', $value);
        }

        throw new InvalidArgumentException('Invalid between value format');
    }

    /**
     * Parse the value for in/not_in (accepts array or string)
     */
    protected function parseArrayValue($value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && str_contains($value, ',')) {
            return explode(',', $value);
        }

        return [$value];
    }

    /**
     * Validate the filters of a segment.
     */
    public function validateFilters(array $filters): array
    {
        $availableFilters = $this->getAvailableFilters();
        $errors = [];

        foreach ($filters as $index => $filter) {
            // Check that the field exists
            if (! isset($filter['type'])) {
                $errors[] = "Filter #{$index}: The 'type' attribute is required";

                continue;
            }

            if (! isset($availableFilters[$filter['type']])) {
                $errors[] = "Filter #{$index}: Filter '{$filter['type']}' is invalid";

                continue;
            }

            $filterConfig = $availableFilters[$filter['type']];

            // Check that the operator exists
            if (! isset($filter['operator'])) {
                $errors[] = "Filter #{$index}: The 'operator' attribute is required";

                continue;
            }

            // Check that the operator is valid for this field
            if (! in_array($filter['operator'], $filterConfig['operators'])) {
                $errors[] = "Filter #{$index}: Operator '{$filter['operator']}' is not valid for field '{$filter['type']}'";
            }

            // Specific validation for between/not_between
            if (in_array($filter['operator'], ['between', 'not_between']) && (! isset($filter['value']) || ! $this->isValidBetweenValue($filter['value']))) {
                $errors[] = "Filter #{$index}: 'between' requires an array of 2 values [min, max]";
            }

            // Specific validation for in/not_in
            if (in_array($filter['operator'], ['in', 'not_in'])) {
                if (! isset($filter['value'])) {
                    $errors[] = "Filter #{$index}: 'value' is required";
                } elseif (! is_array($filter['value']) && ! str_contains($filter['value'], ',')) {
                    $errors[] = "Filter #{$index}: '{$filter['operator']}' requires an array or a comma-separated string";
                }
            }

            // Validation for operators that need a value
            $operatorsNeedingValue = [
                'equals', 'not_equals', 'more_than', 'less_than', 'contains', 'starts_with', 'ends_with', 'before', 'after',
            ];
            if (in_array($filter['operator'], $operatorsNeedingValue) && (! isset($filter['value']) || $filter['value'] === '')) {
                $errors[] = "Filter #{$index}: 'value' is required for operator '{$filter['operator']}'";
            }

            // Check that the condition is AND or OR
            if (isset($filter['condition']) && ! in_array($filter['condition'], ['AND', 'OR'])) {
                $errors[] = "Filter #{$index}: Condition must be 'AND' or 'OR'";
            }
        }

        return $errors;
    }

    /**
     * Check if the value for between is valid.
     */
    protected function isValidBetweenValue($value): bool
    {
        // Array with 2 elements
        if (is_array($value)) {
            if (count($value) === 2 && isset($value[0]) && isset($value[1])) {
                return true;
            }

            return isset($value['min']) && isset($value['max']);
        }

        // String with comma
        if (is_string($value) && str_contains($value, ',')) {
            $parts = explode(',', $value);

            return count($parts) === 2;
        }

        return false;
    }
}
