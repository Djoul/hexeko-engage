<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Database\factories\CategoryFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\factories\ProductFactory;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Product;
use App\Integrations\Vouchers\Amilon\Services\AmilonProductService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Tests\TestCase;

#[Group('vouchers')]
#[Group('amilon')]
class QuickFixProductN1QueryTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_eager_loads_category_relation_to_avoid_n1_queries(): void
    {
        // Arrange
        $merchant = resolve(MerchantFactory::class)->create([
            'merchant_id' => 'TEST_MERCHANT_123',
        ]);
        $category = resolve(CategoryFactory::class)->create();

        // Create 5 products with category
        resolve(ProductFactory::class)->count(5)->create([
            'merchant_id' => $merchant->merchant_id,
            'category_id' => $category->id,
        ]);

        $service = app(AmilonProductService::class);

        // Enable query log to count queries
        DB::enableQueryLog();

        // Act - Get fallback products (when API is down)
        // Use reflection to access protected method
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('getFallbackProducts');
        $method->setAccessible(true);
        $products = $method->invoke($service, $merchant->merchant_id);

        // Access category attribute on each product (this would trigger N+1 without eager loading)
        foreach ($products as $product) {
            $categoryName = $product->category;
        }

        // Get query count
        $queries = DB::getQueryLog();
        $queryCount = count($queries);

        // Assert - Should have only 2 queries: 1 for products, 1 for categories
        // Without eager loading, we'd have 6 queries (1 for products + 5 for each category)
        $this->assertLessThanOrEqual(2, $queryCount,
            "Expected at most 2 queries but got {$queryCount}. This indicates an N+1 query problem."
        );
    }
}
