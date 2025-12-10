<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\ExportTranslationsAction;
use App\Models\TranslationKey;
use App\Models\TranslationValue;
use App\Services\Models\TranslationKeyService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
#[Group('translation-crud')]
class ExportTranslationsActionTest extends TestCase
{
    private ExportTranslationsAction $action;

    private MockInterface $translationKeyService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->translationKeyService = Mockery::mock(TranslationKeyService::class);
        $this->action = new ExportTranslationsAction($this->translationKeyService);
    }

    #[Test]
    public function it_exports_translations_in_flat_structure(): void
    {
        // Arrange
        $interface = 'web';
        $keys = new Collection([
            $this->createTranslationKey('group1', 'key1', [
                'fr-FR' => 'Valeur française 1',
                'en-UK' => 'English value 1',
            ]),
            $this->createTranslationKey('group1', 'key2', [
                'fr-FR' => 'Valeur française 2',
                'en-UK' => 'English value 2',
            ]),
            $this->createTranslationKey('group2', 'key3', [
                'fr-FR' => 'Valeur française 3',
                'en-UK' => 'English value 3',
            ]),
        ]);

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn($keys);

        // Act
        $result = $this->action->execute($interface);

        // Assert
        $this->assertArrayHasKey('translations', $result);
        $translations = $result['translations'];

        // Check structure: translations[group.key][locale] = value
        $this->assertArrayHasKey('group1.key1', $translations);
        $this->assertArrayHasKey('group1.key2', $translations);
        $this->assertArrayHasKey('group2.key3', $translations);

        $this->assertEquals([
            'fr-FR' => 'Valeur française 1',
            'en-UK' => 'English value 1',
        ], $translations['group1.key1']);

        $this->assertEquals([
            'fr-FR' => 'Valeur française 2',
            'en-UK' => 'English value 2',
        ], $translations['group1.key2']);

        $this->assertEquals([
            'fr-FR' => 'Valeur française 3',
            'en-UK' => 'English value 3',
        ], $translations['group2.key3']);
    }

    #[Test]
    public function it_handles_keys_without_group(): void
    {
        // Arrange
        $interface = 'web';
        $keys = new Collection([
            $this->createTranslationKey(null, 'simple_key', [
                'fr-FR' => 'Valeur simple',
                'en-UK' => 'Simple value',
            ]),
            $this->createTranslationKey('group', 'grouped_key', [
                'fr-FR' => 'Valeur groupée',
                'en-UK' => 'Grouped value',
            ]),
        ]);

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn($keys);

        // Act
        $result = $this->action->execute($interface);

        // Assert
        $translations = $result['translations'];
        $this->assertArrayHasKey('simple_key', $translations);
        $this->assertArrayHasKey('group.grouped_key', $translations);

        $this->assertEquals([
            'fr-FR' => 'Valeur simple',
            'en-UK' => 'Simple value',
        ], $translations['simple_key']);
    }

    #[Test]
    public function it_filters_by_specific_locale(): void
    {
        // Arrange
        $interface = 'web';
        $locale = 'fr-FR';
        $keys = new Collection([
            $this->createTranslationKey('group', 'key1', [
                'fr-FR' => 'Valeur française',
                'en-UK' => 'English value',
                'es-ES' => 'Valor español',
            ]),
            $this->createTranslationKey('group', 'key2', [
                'fr-FR' => 'Autre valeur',
                'en-UK' => 'Another value',
            ]),
        ]);

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn($keys);

        // Act
        $result = $this->action->execute($interface, $locale);

        // Assert
        $translations = $result['translations'];

        foreach ($translations as $locales) {
            $this->assertArrayHasKey('fr-FR', $locales);
            $this->assertArrayNotHasKey('en-UK', $locales);
            $this->assertArrayNotHasKey('es-ES', $locales);
        }
    }

    #[Test]
    public function it_handles_missing_translations_with_fallback(): void
    {
        // Arrange
        $interface = 'web';
        $keys = new Collection([
            $this->createTranslationKey('group', 'key1', [
                'en-UK' => 'English value only',
            ]),
            $this->createTranslationKey('group', 'key2', [
                'fr-FR' => 'French value',
                'en-UK' => 'English value',
            ]),
        ]);

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn($keys);

        // Act
        $result = $this->action->execute($interface);

        // Assert
        $translations = $result['translations'];

        // key1 should only have en-UK
        $this->assertArrayHasKey('group.key1', $translations);
        $this->assertArrayHasKey('en-UK', $translations['group.key1']);
        $this->assertArrayNotHasKey('fr-FR', $translations['group.key1']);

        // key2 should have both
        $this->assertArrayHasKey('group.key2', $translations);
        $this->assertArrayHasKey('fr-FR', $translations['group.key2']);
        $this->assertArrayHasKey('en-UK', $translations['group.key2']);
    }

    #[Test]
    public function it_includes_metadata_in_response(): void
    {
        // Arrange
        $interface = 'mobile';
        $keys = new Collection([
            $this->createTranslationKey('group', 'key', [
                'fr-FR' => 'Value',
            ]),
        ]);

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn($keys);

        Carbon::setTestNow('2024-01-15 10:30:00');

        // Act
        $result = $this->action->execute($interface);

        // Assert
        $this->assertEquals('mobile', $result['interface']);
        $this->assertEquals('2024-01-15T10:30:00+00:00', $result['exported_at']);
        $this->assertEquals(1, $result['total_keys']);
        $this->assertEquals(['fr-FR'], $result['locales']);

        Carbon::setTestNow(null);
    }

    #[Test]
    public function it_returns_empty_translations_for_non_existent_interface(): void
    {
        // Arrange
        $interface = 'non-existent';

        $this->translationKeyService
            ->shouldReceive('allForInterface')
            ->with($interface)
            ->once()
            ->andReturn(new Collection);

        // Act
        $result = $this->action->execute($interface);

        // Assert
        $this->assertEquals([], $result['translations']);
        $this->assertEquals(0, $result['total_keys']);
        $this->assertEquals([], $result['locales']);
    }

    private function createTranslationKey(?string $group, string $key, array $values): TranslationKey
    {
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->shouldReceive('getAttribute')->with('group')->andReturn($group);
        $translationKey->shouldReceive('getAttribute')->with('key')->andReturn($key);
        $translationKey->shouldReceive('setAttribute')->andReturnSelf();
        $translationKey->group = $group;
        $translationKey->key = $key;

        $translationValues = Collection::make([]);
        foreach ($values as $locale => $value) {
            $translationValue = Mockery::mock(TranslationValue::class)->makePartial();
            $translationValue->shouldReceive('getAttribute')->with('locale')->andReturn($locale);
            $translationValue->shouldReceive('getAttribute')->with('value')->andReturn($value);
            $translationValue->shouldReceive('setAttribute')->andReturnSelf();
            $translationValue->locale = $locale;
            $translationValue->value = $value;
            $translationValues->push($translationValue);
        }

        $translationKey->shouldReceive('getAttribute')->with('values')->andReturn($translationValues);
        $translationKey->values = $translationValues;

        return $translationKey;
    }
}
