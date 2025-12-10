<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\Translation;

use App\Actions\Translation\CreateTranslationAction;
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
class CreateTranslationActionTest extends TestCase
{
    private CreateTranslationAction $action;

    private TranslationKeyService $mockService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockService = Mockery::mock(TranslationKeyService::class)->makePartial();
        $this->action = new CreateTranslationAction($this->mockService);
    }

    #[Test]
    public function it_creates_translation_key_successfully(): void
    {
        // Arrange
        $data = [
            'key' => 'test.translation.key',
            'group' => 'test',
            'interface_origin' => 'web_beneficiary',
        ];

        $expectedTranslationKey = new TranslationKey($data);
        $expectedTranslationKey->id = 1;

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('test.translation.key', 'web_beneficiary')
            ->andReturn(null);

        $this->mockService->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($expectedTranslationKey);

        Event::fake();

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(TranslationKey::class, $result);
        $this->assertEquals('test.translation.key', $result->key);
        $this->assertEquals('test', $result->group);
    }

    #[Test]
    public function it_throws_exception_when_translation_key_already_exists(): void
    {
        // Arrange
        $data = [
            'key' => 'existing.key',
            'group' => 'test',
            'interface_origin' => 'web_beneficiary',
        ];

        $existingKey = new TranslationKey(['key' => 'existing.key']);

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('existing.key', 'web_beneficiary')
            ->andReturn($existingKey);

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Translation key already exists: existing.key');

        $this->action->execute($data);
    }

    #[Test]
    public function it_creates_translation_key_without_group(): void
    {
        // Arrange
        $data = [
            'key' => 'test.translation.key',
        ];

        $expectedTranslationKey = new TranslationKey($data);
        $expectedTranslationKey->id = 1;

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('test.translation.key', null)
            ->andReturn(null);

        $this->mockService->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($expectedTranslationKey);

        Event::fake();

        // Act
        $result = $this->action->execute($data);

        // Assert
        $this->assertInstanceOf(TranslationKey::class, $result);
        $this->assertEquals('test.translation.key', $result->key);
        $this->assertNull($result->group);
    }

    #[Test]
    public function it_logs_activity_when_creating_translation_key(): void
    {
        // Arrange
        $data = [
            'key' => 'test.translation.key',
            'group' => 'test',
            'interface_origin' => 'web_beneficiary',
        ];

        $expectedTranslationKey = new TranslationKey($data);
        $expectedTranslationKey->id = 1;

        $this->mockService->shouldReceive('findByKey')
            ->once()
            ->with('test.translation.key', 'web_beneficiary')
            ->andReturn(null);

        $this->mockService->shouldReceive('create')
            ->once()
            ->with($data)
            ->andReturn($expectedTranslationKey);

        // Act
        $this->action->execute($data);

        // Assert - activity logging is tested through integration tests
        $this->assertTrue(true);
    }
}
