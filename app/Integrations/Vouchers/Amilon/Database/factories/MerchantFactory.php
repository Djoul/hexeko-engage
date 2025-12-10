<?php

namespace App\Integrations\Vouchers\Amilon\Database\factories;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Integrations\Vouchers\Amilon\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Merchant> */
class MerchantFactory extends Factory
{
    protected $model = Merchant::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'country' => $this->faker->countryCode(),
            'merchant_id' => $this->faker->unique()->regexify('[A-Z0-9]{8}'),
            'description' => $this->faker->text(),
            'image_url' => $this->faker->imageUrl(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Configure the model factory with categories.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Merchant $merchant): void {
            // Only attach categories if they exist and no categories are already attached
            if ($merchant->categories()->count() === 0) {
                $categories = Category::inRandomOrder()->limit(rand(0, 2))->get();
                if ($categories->isNotEmpty()) {
                    $merchant->categories()->attach($categories);
                }
            }
        });
    }

    /**
     * Create a merchant with specific categories.
     */
    public function withCategories(array $categoryNames): static
    {
        return $this->afterCreating(function (Merchant $merchant) use ($categoryNames): void {
            $categories = collect($categoryNames)->map(function ($name): Category {
                return $this->findOrCreateCategoryByName((string) $name);
            });

            $merchant->categories()->sync($categories->pluck('id'));
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
}
