<?php

namespace App\Integrations\Vouchers\Amilon\Helpers;

class SearchHelper
{
    /**
     * Normalize search terms for consistent searching.
     */
    public static function normalizeSearchTerms(string $searchTerm): string
    {
        // Convert to lowercase
        $normalized = strtolower($searchTerm);

        // Remove special characters except spaces and common punctuation
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-\'\.]/u', ' ', $normalized);

        // Remove extra whitespace
        $normalized = trim($normalized ?? '');

        $result = preg_replace('/\s+/', ' ', $normalized);

        return $result ?? '';
    }

    /**
     * Split search terms into individual words.
     *
     * @return array<int, string>
     */
    public static function splitSearchTerms(string $searchTerm): array
    {
        $normalized = self::normalizeSearchTerms($searchTerm);

        if (empty($normalized)) {
            return [];
        }

        $terms = array_filter(explode(' ', $normalized), function ($term): bool {
            return strlen($term) >= 2; // Only include terms with 2+ characters
        });

        return array_values($terms); // Re-index array
    }

    /**
     * Build search query for database with wildcards.
     */
    public static function buildSearchQuery(string $searchTerm): string
    {
        $normalized = self::normalizeSearchTerms($searchTerm);

        if (empty($normalized)) {
            return '';
        }

        return "%{$normalized}%";
    }

    /**
     * Highlight search terms in text.
     */
    public static function highlightSearchTerms(string $text, string $searchTerm): string
    {
        if (empty($searchTerm)) {
            return $text;
        }

        $terms = self::splitSearchTerms($searchTerm);

        foreach ($terms as $term) {
            $result = preg_replace(
                '/('.preg_quote($term, '/').')/i',
                '<mark>$1</mark>',
                $text
            );
            if ($result !== null) {
                $text = $result;
            }
        }

        return $text;
    }
}
