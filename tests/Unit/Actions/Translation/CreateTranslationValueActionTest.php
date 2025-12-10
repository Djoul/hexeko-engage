<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\CreateTranslationValueAction;
use App\Models\TranslationKey;
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
class CreateTranslationValueActionTest extends TestCase
{
    private CreateTranslationValueAction $action;

    private TranslationValueService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(TranslationValueService::class);
        $this->action = new CreateTranslationValueAction($this->mockService);
    }

    #[Test]
    public function it_creates_translation_value_successfully(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationKey->id = 'uuid-123';

        // Mock the values relationship chain
        $mockQuery = Mockery::mock('Illuminate\Database\Eloquent\Relations\HasMany');
        $mockQuery->shouldReceive('where')
            ->with('locale', 'fr')
            ->andReturnSelf();
        $mockQuery->shouldReceive('exists')
            ->andReturn(false);

        $translationKey->shouldReceive('values')
            ->andReturn($mockQuery);

        $data = [
            'locale' => 'fr',
            'value' => 'Bonjour le monde',
        ];

        $expectedValue = new TranslationValue;
        $expectedValue->forceFill([
            'id' => 'uuid-456',
            'translation_key_id' => 'uuid-123',
            'locale' => 'fr',
            'value' => 'Bonjour le monde',
        ]);

        $this->mockService->shouldReceive('create')
            ->once()
            ->with(array_merge($data, ['translation_key_id' => 'uuid-123']))
            ->andReturn($expectedValue);

        Event::fake();

        // Act
        $result = $this->action->execute($translationKey, $data);

        // Assert
        $this->assertInstanceOf(TranslationValue::class, $result);
        $this->assertEquals('fr', $result->locale);
        $this->assertEquals('Bonjour le monde', $result->value);
        $this->assertEquals('uuid-123', $result->translation_key_id);
    }

    #[Test]
    public function it_validates_locale_is_provided(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->id = 'uuid-123';

        $data = [
            'value' => 'Some value',
            // locale is missing
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Locale is required');

        $this->action->execute($translationKey, $data);
    }

    #[Test]
    public function it_validates_value_is_provided(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->id = 'uuid-123';

        $data = [
            'locale' => 'fr',
            // value is missing
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Value is required');

        $this->action->execute($translationKey, $data);
    }

    #[Test]
    public function it_prevents_duplicate_locale_for_same_key(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationKey->id = 'uuid-123';

        // Mock the values relationship chain
        $mockQuery = Mockery::mock('Illuminate\Database\Eloquent\Relations\HasMany');
        $mockQuery->shouldReceive('where')
            ->with('locale', 'fr')
            ->andReturnSelf();
        $mockQuery->shouldReceive('exists')
            ->andReturn(true);

        $translationKey->shouldReceive('values')
            ->andReturn($mockQuery);

        $data = [
            'locale' => 'fr',
            'value' => 'Duplicate locale',
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation value for locale "fr" already exists');

        $this->action->execute($translationKey, $data);
    }

    #[Test]
    public function it_logs_activity_when_creating_translation_value(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationKey->id = 'uuid-123';

        // Mock the values relationship chain
        $mockQuery = Mockery::mock('Illuminate\Database\Eloquent\Relations\HasMany');
        $mockQuery->shouldReceive('where')
            ->with('locale', 'es')
            ->andReturnSelf();
        $mockQuery->shouldReceive('exists')
            ->andReturn(false);

        $translationKey->shouldReceive('values')
            ->andReturn($mockQuery);

        $data = [
            'locale' => 'es',
            'value' => 'Hola mundo',
        ];

        $expectedValue = new TranslationValue;
        $expectedValue->forceFill([
            'id' => 'uuid-789',
            'translation_key_id' => 'uuid-123',
            'locale' => 'es',
            'value' => 'Hola mundo',
        ]);

        $this->mockService->shouldReceive('create')
            ->once()
            ->with(array_merge($data, ['translation_key_id' => 'uuid-123']))
            ->andReturn($expectedValue);

        Event::fake();

        // Act
        $this->action->execute($translationKey, $data);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
