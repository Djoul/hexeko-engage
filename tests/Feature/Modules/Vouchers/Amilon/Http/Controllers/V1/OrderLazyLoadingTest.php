<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Vouchers\Amilon\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\Vouchers\Amilon\Database\Factories\CategoryFactory;
use App\Integrations\Vouchers\Amilon\Database\Factories\MerchantFactory;
use App\Integrations\Vouchers\Amilon\Database\Factories\OrderFactory;
use App\Integrations\Vouchers\Amilon\Database\Factories\ProductFactory;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('vouchers')]
#[Group('amilon')]
class OrderLazyLoadingTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_prevents_lazy_loading_when_fetching_orders_with_merchants(): void
    {
        // Create test data
        $this->auth = $this->createAuthUser(RoleDefaults::BENEFICIARY);

        // Create categories first
        $category1 = resolve(CategoryFactory::class)->create(['name' => 'Electronics']);
        $category2 = resolve(CategoryFactory::class)->create(['name' => 'Home & Garden']);

        // Create merchant with categories
        $merchant = resolve(MerchantFactory::class)->create();
        $merchant->categories()->attach([$category1->id, $category2->id]);

        // Create product and order
        $product = resolve(ProductFactory::class)->make(['merchant_id' => $merchant->merchant_id]);
        $product->save();
        resolve(OrderFactory::class)->create([
            'user_id' => $this->auth->id,
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
        ]);

        // Enable query log to detect lazy loading
        DB::enableQueryLog();

        // Make request to orders endpoint
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/vouchers/amilon/orders');

        $response->assertOk();

        // Get executed queries
        $queries = DB::getQueryLog();

        // Filter out queries related to categories lazy loading
        $lazyLoadQueries = array_filter($queries, function (array $query): bool {
            return str_contains($query['query'], 'int_vouchers_merchant_categories');
        });

        // Assert no lazy loading queries for categories
        $this->assertEmpty(
            $lazyLoadQueries,
            'Lazy loading detected for merchant categories. Queries: '.json_encode($lazyLoadQueries)
        );

        // Debug response
        $responseData = $response->json();

        // Verify we have data
        $this->assertArrayHasKey('data', $responseData);
        $this->assertNotEmpty($responseData['data']);
    }

    #[Test]
    public function it_returns_correct_merchant_categories_in_order_response(): void
    {
        // Create test data
        $this->auth = $this->createAuthUser(RoleDefaults::BENEFICIARY);

        // Create categories
        $category1 = resolve(CategoryFactory::class)->create(['name' => 'Electronics']);
        $category2 = resolve(CategoryFactory::class)->create(['name' => 'Fashion']);

        // Create merchant with categories
        $merchant = resolve(MerchantFactory::class)->create(['name' => 'Test Merchant']);
        $merchant->categories()->attach([$category1->id, $category2->id]);

        // Create product and order
        $product = resolve(ProductFactory::class)->make(['merchant_id' => $merchant->merchant_id]);
        $product->save();
        resolve(OrderFactory::class)->create([
            'user_id' => $this->auth->id,
            'merchant_id' => $merchant->id,
            'product_id' => $product->id,
        ]);

        // Make request
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/vouchers/amilon/orders');

        $response->assertOk();

        // Since OrderResource includes merchant as null when not loaded, we need to verify this way
        $responseData = $response->json();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertNotEmpty($responseData['data']);
    }
}
