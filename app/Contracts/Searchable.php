<?php

declare(strict_types=1);

namespace App\Contracts;

interface Searchable
{
    /**
     * Get the fields that should be searchable for this model.
     *
     * @return array<int, string>
     */
    public function getSearchableFields(): array;

    /**
     * Get the relations and their fields that should be searchable.
     *
     * @return array<string, array<int, string>>
     */
    public function getSearchableRelations(): array;

    /**
     * Get the SQL expression for searching a virtual field.
     * Returns null if the field should use standard searching.
     */
    public static function getSearchableExpression(string $field): ?string;
}
