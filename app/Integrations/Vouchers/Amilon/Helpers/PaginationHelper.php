<?php

namespace App\Integrations\Vouchers\Amilon\Helpers;

class PaginationHelper
{
    /**
     * Calculate offset based on page and per_page parameters.
     */
    public static function calculateOffset(int $page, int $perPage): int
    {
        if ($page < 1) {
            $page = 1;
        }

        return ($page - 1) * $perPage;
    }

    /**
     * Validate page limits and return corrected values.
     *
     * @return array<string, int>
     */
    public static function validatePageLimits(int $page, int $perPage, int $maxPerPage = 100): array
    {
        // Ensure page is at least 1
        if ($page < 1) {
            $page = 1;
        }

        // Ensure per_page is at least 1 and not more than max
        if ($perPage < 1) {
            $perPage = 10; // Default per page
        }

        if ($perPage > $maxPerPage) {
            $perPage = $maxPerPage;
        }

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => self::calculateOffset($page, $perPage),
        ];
    }

    /**
     * Calculate total pages based on total items and per page.
     */
    public static function calculateTotalPages(int $totalItems, int $perPage): int
    {
        if ($perPage <= 0) {
            return 0;
        }

        return (int) ceil($totalItems / $perPage);
    }
}
