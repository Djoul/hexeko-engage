<?php

namespace Tests\Unit\Actions\Integrations\Vouchers\Amilon;

use App\Actions\Integrations\Vouchers\Amilon\CreateOrUpdateTranslatedCategoryAction;
use App\Integrations\Vouchers\Amilon\Models\Category;
use App\Settings\General\LocalizationSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('amilon')]
#[Group('vouchers')]
class CreateOrUpdateTranslatedCategoryActionTest extends TestCase
{
    use DatabaseTransactions;

    private CreateOrUpdateTranslatedCategoryAction $action;

    private LocalizationSettings $localizationSettings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->localizationSettings = app(LocalizationSettings::class);
        $this->action = new CreateOrUpdateTranslatedCategoryAction($this->localizationSettings);
    }

    #[Test]
    public function it_creates_a_new_category_with_translations(): void
    {
        // Arrange
        $categoryId = (string) Str::uuid();
        $defaultName = 'Test Category';
        $initialCount = Category::count();

        // Act
        $result = $this->action->execute($categoryId, $defaultName);

        // Assert
        $this->assertEquals($initialCount + 1, Category::count());
        $this->assertTrue($result['wasCreated']);
        $this->assertInstanceOf(Category::class, $result['category']);
        $this->assertEquals($categoryId, $result['category']->id);

        // Check all locales have the default name
        foreach ($this->localizationSettings->available_locales as $locale) {
            $this->assertEquals($defaultName, $result['category']->getTranslation('name', $locale));
        }
    }

    #[Test]
    public function it_updates_existing_category_without_overwriting_translations(): void
    {
        // Arrange
        $categoryId = (string) Str::uuid();
        $defaultName = 'Original Name';

        // Create initial category with translations
        $initialResult = $this->action->execute($categoryId, $defaultName);
        $category = $initialResult['category'];

        // Update specific translation
        $customFrenchName = 'Nom Personnalisé';
        $category->setTranslation('name', 'fr', $customFrenchName);
        $category->save();

        $initialCount = Category::count();

        // Act - Try to update with new default name
        $result = $this->action->execute($categoryId, 'New Default Name');

        // Assert
        $this->assertEquals($initialCount, Category::count()); // No new category created
        $this->assertFalse($result['wasCreated']);
        $this->assertEquals($categoryId, $result['category']->id);

        // French translation should be preserved
        $this->assertEquals($customFrenchName, $result['category']->getTranslation('name', 'fr'));

        // Other locales without custom translation get the new default
        foreach ($this->localizationSettings->available_locales as $locale) {
            if ($locale !== 'fr') {
                $this->assertEquals('New Default Name', $result['category']->getTranslation('name', $locale));
            }
        }
    }

    #[Test]
    public function it_handles_multiple_locales_correctly(): void
    {
        // Arrange
        $categoryId = (string) Str::uuid();
        $defaultName = 'Multi Locale Test';

        // Act
        $result = $this->action->execute($categoryId, $defaultName);

        // Assert
        $availableLocales = $this->localizationSettings->available_locales;
        $this->assertGreaterThan(0, count($availableLocales));

        foreach ($availableLocales as $locale) {
            $translation = $result['category']->getTranslation('name', $locale);
            $this->assertNotEmpty($translation);
            $this->assertEquals($defaultName, $translation);
        }
    }
}
