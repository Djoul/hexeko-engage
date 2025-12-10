<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Helpers\PaginationHelper;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
#[Group('helper')]
class PaginationHelperTest extends TestCase
{
    #[Test]
    public function test_pagination_helper_calculate_offset(): void
    {
        // Test basic offset calculation
        $this->assertEquals(0, PaginationHelper::calculateOffset(1, 10));
        $this->assertEquals(10, PaginationHelper::calculateOffset(2, 10));
        $this->assertEquals(20, PaginationHelper::calculateOffset(3, 10));
        $this->assertEquals(50, PaginationHelper::calculateOffset(6, 10));

        // Test with different per_page values
        $this->assertEquals(0, PaginationHelper::calculateOffset(1, 5));
        $this->assertEquals(5, PaginationHelper::calculateOffset(2, 5));
        $this->assertEquals(0, PaginationHelper::calculateOffset(1, 25));
        $this->assertEquals(25, PaginationHelper::calculateOffset(2, 25));

        // Test edge cases
        $this->assertEquals(0, PaginationHelper::calculateOffset(0, 10)); // Page 0 should be treated as 1
        $this->assertEquals(0, PaginationHelper::calculateOffset(-1, 10)); // Negative page should be treated as 1
    }

    #[Test]
    public function test_pagination_helper_validate_page_limits(): void
    {
        // Test normal values
        $result = PaginationHelper::validatePageLimits(2, 10);
        $this->assertEquals([
            'page' => 2,
            'per_page' => 10,
            'offset' => 10,
        ], $result);

        // Test page correction (negative or zero)
        $result = PaginationHelper::validatePageLimits(0, 10);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 10,
            'offset' => 0,
        ], $result);

        $result = PaginationHelper::validatePageLimits(-5, 10);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 10,
            'offset' => 0,
        ], $result);

        // Test per_page correction (too small)
        $result = PaginationHelper::validatePageLimits(1, 0);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 10,
            'offset' => 0,
        ], $result);

        $result = PaginationHelper::validatePageLimits(1, -5);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 10,
            'offset' => 0,
        ], $result);

        // Test per_page correction (too large with default max)
        $result = PaginationHelper::validatePageLimits(1, 150);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 100,
            'offset' => 0,
        ], $result);

        // Test per_page correction (too large with custom max)
        $result = PaginationHelper::validatePageLimits(1, 150, 50);
        $this->assertEquals([
            'page' => 1,
            'per_page' => 50,
            'offset' => 0,
        ], $result);
    }

    #[Test]
    public function test_pagination_helper_calculate_total_pages(): void
    {
        // Test normal calculations
        $this->assertEquals(10, PaginationHelper::calculateTotalPages(100, 10));
        $this->assertEquals(5, PaginationHelper::calculateTotalPages(50, 10));
        $this->assertEquals(3, PaginationHelper::calculateTotalPages(21, 10)); // Should round up
        $this->assertEquals(1, PaginationHelper::calculateTotalPages(5, 10));

        // Test edge cases
        $this->assertEquals(0, PaginationHelper::calculateTotalPages(0, 10)); // No items
        $this->assertEquals(0, PaginationHelper::calculateTotalPages(100, 0)); // Invalid per_page
        $this->assertEquals(0, PaginationHelper::calculateTotalPages(100, -5)); // Invalid per_page

        // Test exact divisions
        $this->assertEquals(10, PaginationHelper::calculateTotalPages(100, 10));
        $this->assertEquals(4, PaginationHelper::calculateTotalPages(20, 5));
    }
}
