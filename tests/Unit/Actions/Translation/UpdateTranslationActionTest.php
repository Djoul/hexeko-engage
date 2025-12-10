<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\UpdateTranslationAction;
use App\Models\TranslationKey;
use App\Services\Models\TranslationKeyService;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('translation')]
#[Group('translation-crud')]
class UpdateTranslationActionTest extends TestCase
{
    private UpdateTranslationAction $action;

    private TranslationKeyService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(TranslationKeyService::class);
        $this->action = new UpdateTranslationAction($this->mockService);
    }

    #[Test]
    public function it_updates_translation_key_successfully(): void
    {
        // Arrange
        $translationKey = new TranslationKey;
        $translationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'old.translation.key',
            'group' => 'old',
            'interface_origin' => 'web_financer',
        ]);

        $data = [
            'key' => 'new.translation.key',
            'group' => 'new',
            'interface_origin' => 'web_financer',
        ];

        $updatedTranslationKey = new TranslationKey;
        $updatedTranslationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'new.translation.key',
            'group' => 'new',
            'interface_origin' => 'web_financer',
        ]);

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('new.translation.key', 'web_financer')
            ->andReturn(null);

        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationKey, $data)
            ->andReturn($updatedTranslationKey);

        Event::fake();

        // Act
        $result = $this->action->execute($translationKey, $data);

        // Assert
        $this->assertInstanceOf(TranslationKey::class, $result);
        $this->assertEquals('new.translation.key', $result->key);
        $this->assertEquals('new', $result->group);
    }

    #[Test]
    public function it_throws_exception_when_new_key_already_exists(): void
    {
        // Arrange
        $translationKey = Mockery::mock(TranslationKey::class)->makePartial();
        $translationKey->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-123');
        $translationKey->shouldReceive('getAttribute')
            ->with('key')
            ->andReturn('old.translation.key');
        $translationKey->shouldReceive('getAttribute')
            ->with('interface_origin')
            ->andReturn('web_financer');
        $translationKey->id = 'uuid-123';
        $translationKey->key = 'old.translation.key';
        $translationKey->interface_origin = 'web_financer';

        $data = [
            'key' => 'existing.key',
            'interface_origin' => 'web_financer',
        ];

        $existingKey = Mockery::mock(TranslationKey::class)->makePartial();
        $existingKey->shouldReceive('getAttribute')
            ->with('id')
            ->andReturn('uuid-456');
        $existingKey->id = 'uuid-456';

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('existing.key', 'web_financer')
            ->andReturn($existingKey);

        $this->mockService->shouldReceive('update')
            ->never();

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key already exists: existing.key');

        $this->action->execute($translationKey, $data);
    }

    #[Test]
    public function it_allows_updating_same_key(): void
    {
        // Arrange
        $translationKey = new TranslationKey;
        $translationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'same.key',
            'group' => 'old',
        ]);

        $data = [
            'key' => 'same.key',
            'group' => 'new',
        ];

        $updatedTranslationKey = new TranslationKey;
        $updatedTranslationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'same.key',
            'group' => 'new',
        ]);

        // No findByKey call expected since key doesn't change
        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationKey, $data)
            ->andReturn($updatedTranslationKey);

        Event::fake();

        // Act
        $result = $this->action->execute($translationKey, $data);

        // Assert
        $this->assertInstanceOf(TranslationKey::class, $result);
        $this->assertEquals('same.key', $result->key);
        $this->assertEquals('new', $result->group);
    }

    #[Test]
    public function it_updates_only_provided_fields(): void
    {
        // Arrange
        $translationKey = new TranslationKey;
        $translationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'test.key',
            'group' => 'test',
        ]);

        $data = [
            'group' => 'updated',
        ];

        $updatedTranslationKey = new TranslationKey;
        $updatedTranslationKey->forceFill([
            'id' => 'uuid-123',
            'key' => 'test.key',
            'group' => 'updated',
        ]);

        $this->mockService->shouldReceive('update')
            ->once()
            ->with($translationKey, $data)
            ->andReturn($updatedTranslationKey);

        Event::fake();

        // Act
        $result = $this->action->execute($translationKey, $data);

        // Assert
        $this->assertInstanceOf(TranslationKey::class, $result);
        $this->assertEquals('test.key', $result->key);
        $this->assertEquals('updated', $result->group);
    }
}
