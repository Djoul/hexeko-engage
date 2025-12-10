<?php

namespace App\Integrations\Vouchers\Amilon\Database\factories;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use App\Integrations\Vouchers\Amilon\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * NOTE: All monetary values (price, net_price, discount) are stored in CENTS.
     */
    public function definition(): array
    {
        // Generate prices in cents (multiply euros by 100)
        $priceInCents = $this->faker->numberBetween(1000, 50000); // 10€ to 500€
        $netPriceInCents = $this->faker->numberBetween(800, (int) ($priceInCents * 0.9)); // 8€ to 90% of price
        $discountInCents = $priceInCents - $netPriceInCents;

        return [
            'merchant_id' => null, // Will be set via forMerchant($merchant) method
            'category_id' => null, // Will be set via withCategory() method or auto-created
            'product_code' => $this->faker->unique()->regexify('[A-Z0-9]{8}'),
            'name' => $this->faker->words(3, true),
            'price' => $priceInCents,           // Price in cents
            'net_price' => $netPriceInCents,    // Net price in cents
            'discount' => $discountInCents,     // Discount in cents
            'currency' => 'EUR',
            'country' => 'FR',
            'description' => $this->faker->sentence(),
            'image_url' => $this->faker->imageUrl(),
            'is_available' => true, // Product should be available by default
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }

    /**
     * Create a product for a specific merchant.
     */
    public function forMerchant(Merchant $merchant): static
    {
        return $this->state(function (array $attributes) use ($merchant): array {
            return [
                'merchant_id' => $merchant->merchant_id,
            ];
        });
    }

    /**
     * Create a product with a specific category.
     */
    public function withCategory(string $categoryName): static
    {
        return $this->state(function (array $attributes) use ($categoryName): array {
            $category = $this->findOrCreateCategoryByName($categoryName);

            return [
                'category_id' => $category->id,
            ];
        });
    }

    /**
     * Find or create a category by name (handling translations).
     */
    private function findOrCreateCategoryByName(string $categoryName): Category
    {
        // Try to find existing category by checking all translations
        $existingCategory = Category::all()->first(function (Category $category) use ($categoryName): bool {
            $translations = $category->getTranslations('name');

            return in_array($categoryName, $translations, true);
        });

        if ($existingCategory) {
            return $existingCategory;
        }

        // Create new category with translations using the action
        $action = app(CreateOrUpdateTranslatedCategoryAction::class);
        $result = $action->execute((string) Str::uuid(), $categoryName);

        return $result['category'];
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Product $product): void {
            // If no merchant_id is set, create a merchant with category
            if (! $product->merchant_id) {
                $merchant = Merchant::factory()->create();
                $product->merchant_id = $merchant->merchant_id;
            }

            // If no category_id is set, try to get one from merchant or create default
            if (! $product->category_id) {
                // Try to get merchant's first category
                $merchant = Merchant::where('merchant_id', $product->merchant_id)->first();
                if ($merchant && $merchant->categories->isNotEmpty()) {
                    $product->category_id = $merchant->categories->first()->id;
                } else {
                    // Create a default category with proper translations
                    $category = $this->findOrCreateCategoryByName('General');
                    $product->category_id = $category->id;

                    // Attach category to merchant if exists
                    if ($merchant) {
                        $merchant->categories()->syncWithoutDetaching([$category->id]);
                    }
                }
            }
        });
    }
}
