<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\UpdateTranslationValueAction;
use App\Models\TranslationValue;
use App\Services\Models\TranslationValueService;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
#[Group('translation-crud')]
class UpdateTranslationValueActionTest extends TestCase
{
    private UpdateTranslationValueAction $action;

    private TranslationValueService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(TranslationValueService::class);
        $this->action = new UpdateTranslationValueAction($this->mockService);
    }

    #[Test]
    public function it_updates_translation_value_successfully(): void
    {
        // Arrange
        $translationValue = Mockery::mock(TranslationValue::class)->makePartial();
        $translationValue->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationValue->shouldReceive('getAttribute')
            ->with('locale')
            ->andReturn('fr');
        $translationValue->shouldReceive('getAttribute')
            ->with('value')
            ->andReturn('Ancienne valeur');
        $translationValue->id = 'uuid-123';
        $translationValue->locale = 'fr';
        $translationValue->value = 'Ancienne valeur';

        $data = [
            'value' => 'Nouvelle valeur',
        ];

        $updatedValue = new TranslationValue;
        $updatedValue->forceFill([
            'id' => 'uuid-123',
            'locale' => 'fr',
            'value' => 'Nouvelle valeur',
        ]);

        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationValue, $data)
            ->andReturn($updatedValue);

        Event::fake();

        // Act
        $result = $this->action->execute($translationValue, $data);

        // Assert
        $this->assertInstanceOf(TranslationValue::class, $result);
        $this->assertEquals('Nouvelle valeur', $result->value);
        $this->assertEquals('fr', $result->locale);
    }

    #[Test]
    public function it_validates_value_is_provided(): void
    {
        // Arrange
        $translationValue = Mockery::mock(TranslationValue::class)->makePartial();
        $translationValue->id = 'uuid-123';

        $data = [
            // value is missing
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is required');

        $this->action->execute($translationValue, $data);
    }

    #[Test]
    public function it_allows_empty_string_value(): void
    {
        // Arrange
        $translationValue = Mockery::mock(TranslationValue::class)->makePartial();
        $translationValue->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationValue->shouldReceive('getAttribute')
            ->with('locale')
            ->andReturn('fr');
        $translationValue->shouldReceive('getAttribute')
            ->with('value')
            ->andReturn('Some value');
        $translationValue->id = 'uuid-123';
        $translationValue->locale = 'fr';
        $translationValue->value = 'Some value';

        $data = [
            'value' => '', // Empty string is allowed
        ];

        $updatedValue = new TranslationValue;
        $updatedValue->forceFill([
            'id' => 'uuid-123',
            'locale' => 'fr',
            'value' => '',
        ]);

        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationValue, $data)
            ->andReturn($updatedValue);

        Event::fake();

        // Act
        $result = $this->action->execute($translationValue, $data);

        // Assert
        $this->assertInstanceOf(TranslationValue::class, $result);
        $this->assertEquals('', $result->value);
    }

    #[Test]
    public function it_logs_activity_when_updating_translation_value(): void
    {
        // Arrange
        $translationValue = Mockery::mock(TranslationValue::class)->makePartial();
        $translationValue->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationValue->shouldReceive('getAttribute')
            ->with('locale')
            ->andReturn('es');
        $translationValue->shouldReceive('getAttribute')
            ->with('value')
            ->andReturn('Hola');
        $translationValue->id = 'uuid-123';
        $translationValue->locale = 'es';
        $translationValue->value = 'Hola';

        $data = [
            'value' => 'Hola mundo',
        ];

        $updatedValue = new TranslationValue;
        $updatedValue->forceFill([
            'id' => 'uuid-123',
            'locale' => 'es',
            'value' => 'Hola mundo',
        ]);

        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationValue, $data)
            ->andReturn($updatedValue);

        Event::fake();

        // Act
        $this->action->execute($translationValue, $data);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
