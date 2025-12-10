<?php

namespace App\Actions\Integrations\Vouchers\Amilon;

use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Settings\General\LocalizationSettings;

class CreateOrUpdateTranslatedCategoryAction
{
    public function __construct(
        private readonly LocalizationSettings $localizationSettings
    ) {}

    /**
     * Create or update a category with translated name.
     *
     * @param  string  $categoryId  The category ID from Amilon API
     * @param  string  $defaultName  The default name from Amilon API
     * @return array{category: Category, wasCreated: bool} The created/updated category and whether it was newly created
     */
    public function execute(string $categoryId, string $defaultName): array
    {
        $availableLocales = $this->localizationSettings->available_locales;

        // Check if category exists
        $existingCategory = Category::find($categoryId);
        $wasCreated = $existingCategory === null;

        if ($wasCreated) {
            // For new categories, use the default name for all locales
            $translatedName = [];
            foreach ($availableLocales as $locale) {
                $translatedName[$locale] = $defaultName;
            }

            // Create category
            $category = Category::create([
                'id' => $categoryId,
                'name' => $translatedName,
            ]);
        } else {
            // For existing categories, detect the "default" value (most common translation)
            // and only update translations that still have this default value
            $category = $existingCategory;
            $translations = $category->getTranslations('name');

            // Find the most common translation (this is likely the default from Amilon)
            $valueCounts = array_count_values($translations);
            arsort($valueCounts);
            $oldDefaultValue = array_key_first($valueCounts);

            // Update only translations that still have the old default value
            foreach ($availableLocales as $locale) {
                $currentValue = $category->getTranslation('name', $locale);
                if ($currentValue === $oldDefaultValue) {
                    $category->setTranslation('name', $locale, $defaultName);
                }
                // If the translation has a custom value (different from old default), preserve it
            }
            $category->save();
        }

        return [
            'category' => $category,
            'wasCreated' => $wasCreated,
        ];
    }
}
