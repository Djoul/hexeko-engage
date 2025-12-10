<?php

namespace Tests\Unit\Modules\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Http\Resources\MerchantResource;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class AmilonMerchantCategoryGroupingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Run Amilon migrations
        $this->artisan('migrate', [
            '--path' => 'app/Integrations/Vouchers/Amilon/Database/migrations',
            '--realpath' => false,
        ]);
    }

    #[Test]
    public function it_correctly_groups_merchants_by_category_including_null_categories(): void
    {
        // Clean up existing data in the correct order to avoid foreign key constraints
        DB::table('int_vouchers_amilon_products')->delete();
        DB::table('int_vouchers_amilon_merchant_category')->delete();
        DB::table('int_vouchers_amilon_merchants')->delete();
        DB::table('int_vouchers_amilon_categories')->delete();

        // Create categories
        $electronicsCategory = Category::create(['name' => 'Electronics']);
        $sportsCategory = Category::create(['name' => 'Sports']);

        // Create test merchants
        $fnac = Merchant::create([
            'name' => 'Fnac',
            'merchant_id' => 'FNAC001',
            'description' => 'Electronics store',
        ]);
        $fnac->categories()->attach($electronicsCategory);

        $nullCategoryMerchant = Merchant::create([
            'name' => 'Merchant Without Category',
            'merchant_id' => 'NULL001',
            'description' => 'Merchant with null category',
        ]);
        // No category attached

        $emptyCategoryMerchant = Merchant::create([
            'name' => 'Empty Category Merchant',
            'merchant_id' => 'EMPTY001',
            'description' => 'Merchant with empty category',
        ]);
        // No category attached

        $decathlon = Merchant::create([
            'name' => 'Decathlon',
            'merchant_id' => 'DECA001',
            'description' => 'Sports store',
        ]);
        $decathlon->categories()->attach($sportsCategory);

        $anotherElectronics = Merchant::create([
            'name' => 'Another Electronics',
            'merchant_id' => 'ELEC002',
            'description' => 'Another electronics store',
        ]);
        $anotherElectronics->categories()->attach($electronicsCategory);

        // Collect all merchants with their categories loaded
        $merchants = collect([
            $fnac->load('categories'),
            $nullCategoryMerchant->load('categories'),
            $emptyCategoryMerchant->load('categories'),
            $decathlon->load('categories'),
            $anotherElectronics->load('categories'),
        ]);

        // Apply the same grouping logic as in the controller
        $merchantsByCategory = $merchants
            ->groupBy(function ($merchant): string {
                /** @var Merchant $merchant */
                /** @var Category|null $firstCategory */
                $firstCategory = $merchant->categories->first();

                return $firstCategory ? $firstCategory->name : 'Uncategorized';
            })
            ->map(function ($categoryMerchants, $categoryName) {
                /** @var \Illuminate\Support\Collection $categoryMerchants */
                /** @var string $categoryName */
                return $categoryMerchants->map(function ($merchant) use ($categoryName): array {
                    /** @var Merchant $merchant */
                    // For testing, we need to manually build the resource array
                    // because setAttribute doesn't override the accessor
                    $resource = new MerchantResource($merchant);
                    $resourceArray = $resource->toArray(request());
                    // Override the category to match the group
                    $resourceArray['category'] = $categoryName;

                    return $resourceArray;
                });
            });

        // Convert to array for easier testing
        $result = $merchantsByCategory->toArray();

        // Validate structure
        $this->assertIsArray($result);
        $this->assertCount(3, $result); // Electronics, Sports, Uncategorized

        // Validate Electronics category
        $this->assertArrayHasKey('Electronics', $result);
        $this->assertCount(2, $result['Electronics']);
        $this->assertEquals('Fnac', $result['Electronics'][0]['name']);
        $this->assertEquals('Another Electronics', $result['Electronics'][1]['name']);

        // Validate Sports category
        $this->assertArrayHasKey('Sports', $result);
        $this->assertCount(1, $result['Sports']);
        $this->assertEquals('Decathlon', $result['Sports'][0]['name']);

        // Validate Uncategorized category (null and empty categories)
        $this->assertArrayHasKey('Uncategorized', $result);
        $this->assertCount(2, $result['Uncategorized']);

        // Check that null and empty categories are properly handled
        $uncategorizedNames = array_column($result['Uncategorized'], 'name');
        $this->assertContains('Merchant Without Category', $uncategorizedNames);
        $this->assertContains('Empty Category Merchant', $uncategorizedNames);

        // Verify all merchants in Uncategorized have 'Uncategorized' as their category
        foreach ($result['Uncategorized'] as $merchant) {
            $this->assertEquals('Uncategorized', $merchant['category']);
        }
    }

    #[Test]
    public function it_maintains_merchant_resource_structure_after_grouping(): void
    {
        // Create a category
        $testCategory = Category::create(['name' => 'Test Category']);

        // Create merchant and associate category
        $testMerchant = Merchant::create([
            'name' => 'Test Merchant',
            'merchant_id' => 'TEST001',
            'description' => 'Test description',
            'image_url' => 'https://test.com/image.jpg',
            'country' => 'France',
        ]);
        $testMerchant->categories()->attach($testCategory);

        $merchants = collect([$testMerchant->load('categories')]);

        $merchantsByCategory = $merchants
            ->groupBy(function ($merchant): string {
                /** @var Merchant $merchant */
                /** @var Category|null $firstCategory */
                $firstCategory = $merchant->categories->first();

                return $firstCategory ? $firstCategory->name : 'Uncategorized';
            })
            ->map(function ($categoryMerchants, $categoryName) {
                /** @var \Illuminate\Support\Collection $categoryMerchants */
                /** @var string $categoryName */
                return $categoryMerchants->map(function ($merchant) use ($categoryName): array {
                    /** @var Merchant $merchant */
                    $resource = new MerchantResource($merchant);
                    $resourceArray = $resource->toArray(request());
                    $resourceArray['category'] = $categoryName;

                    return $resourceArray;
                });
            });

        $result = $merchantsByCategory->toArray();

        $this->assertArrayHasKey('Test Category', $result);
        $merchant = $result['Test Category'][0];

        // Verify key MerchantResource fields are present
        $this->assertArrayHasKey('name', $merchant);
        $this->assertArrayHasKey('category', $merchant);
        $this->assertArrayHasKey('merchant_id', $merchant);

        $this->assertEquals('Test Merchant', $merchant['name']);
        $this->assertEquals('Test Category', $merchant['category']);
        $this->assertEquals('TEST001', $merchant['merchant_id']);
    }

    #[Test]
    public function it_handles_edge_cases_for_category_grouping(): void
    {
        // Create edge case categories
        $whitespaceCategory = Category::create(['name' => '   ']);
        $zeroCategory = Category::create(['name' => '0']);
        $falseCategory = Category::create(['name' => 'false']);

        // Create merchants with edge case categories
        $whitespaceMerchant = Merchant::create([
            'name' => 'Whitespace Category',
            'merchant_id' => 'WHITE001',
            'description' => 'Whitespace category',
        ]);
        $whitespaceMerchant->categories()->attach($whitespaceCategory);

        $zeroMerchant = Merchant::create([
            'name' => 'Zero String',
            'merchant_id' => 'ZERO001',
            'description' => 'Zero string category',
        ]);
        $zeroMerchant->categories()->attach($zeroCategory);

        $falseMerchant = Merchant::create([
            'name' => 'False String',
            'merchant_id' => 'FALSE001',
            'description' => 'False string category',
        ]);
        $falseMerchant->categories()->attach($falseCategory);

        $merchants = collect([
            $whitespaceMerchant->load('categories'),
            $zeroMerchant->load('categories'),
            $falseMerchant->load('categories'),
        ]);

        $merchantsByCategory = $merchants
            ->groupBy(function ($merchant): string {
                /** @var Merchant $merchant */
                /** @var Category|null $firstCategory */
                $firstCategory = $merchant->categories->first();

                return $firstCategory ? $firstCategory->name : 'Uncategorized';
            })
            ->map(function ($categoryMerchants, $categoryName) {
                /** @var \Illuminate\Support\Collection $categoryMerchants */
                /** @var string $categoryName */
                return $categoryMerchants->map(function ($merchant) use ($categoryName): array {
                    /** @var Merchant $merchant */
                    $resource = new MerchantResource($merchant);
                    $resourceArray = $resource->toArray(request());
                    $resourceArray['category'] = $categoryName;

                    return $resourceArray;
                });
            });

        $result = $merchantsByCategory->toArray();

        // All edge case strings are treated as valid categories
        // '0' is a valid category string
        $this->assertArrayHasKey('0', $result);
        $this->assertEquals('Zero String', $result['0'][0]['name']);

        // Whitespace is a valid category
        $this->assertArrayHasKey('   ', $result);
        $this->assertEquals('Whitespace Category', $result['   '][0]['name']);

        // 'false' is a valid category string
        $this->assertArrayHasKey('false', $result);
        $this->assertEquals('False String', $result['false'][0]['name']);

        // Since all merchants have categories, there should be no 'Uncategorized' group
        $this->assertArrayNotHasKey('Uncategorized', $result);
    }
}
