<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Actions\ToggleArticleFavoriteAction;
use App\Integrations\InternalCommunication\DTOs\ToggleArticleFavoriteDTO;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Services\ArticleInteractionService;
use Exception;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('internal-communication')]
#[Group('article')]
#[Group('interaction')]
class ToggleArticleFavoriteActionTest extends TestCase
{
    private ToggleArticleFavoriteAction $action;

    private MockInterface $articleInteractionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleInteractionService = Mockery::mock(ArticleInteractionService::class);
        $this->action = new ToggleArticleFavoriteAction($this->articleInteractionService);
    }

    #[Test]
    public function it_creates_new_favorite_interaction_when_none_exists(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new ToggleArticleFavoriteDTO(
            userId: $userId,
            articleId: $articleId
        );

        $expectedInteraction = new ArticleInteraction;
        $expectedInteraction->id = 'interaction-789';
        $expectedInteraction->user_id = $userId;
        $expectedInteraction->article_id = $articleId;
        $expectedInteraction->is_favorite = true;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn(null);

        $this->articleInteractionService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($userId, $articleId): bool {
                return $data['user_id'] === $userId
                    && $data['article_id'] === $articleId
                    && $data['is_favorite'] === true;

            }))
            ->andReturn($expectedInteraction);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(ArticleInteraction::class, $result);
        $this->assertTrue($result->is_favorite);
    }

    #[Test]
    public function it_toggles_existing_favorite_from_true_to_false(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new ToggleArticleFavoriteDTO(
            userId: $userId,
            articleId: $articleId
        );

        $existingInteraction = new ArticleInteraction;
        $existingInteraction->id = 'interaction-789';
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->is_favorite = true;

        $updatedInteraction = clone $existingInteraction;
        $updatedInteraction->is_favorite = false;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        $this->articleInteractionService
            ->shouldReceive('update')
            ->once()
            ->with($existingInteraction, ['is_favorite' => false])
            ->andReturn($updatedInteraction);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(ArticleInteraction::class, $result);
        $this->assertFalse($result->is_favorite);
    }

    #[Test]
    public function it_toggles_existing_favorite_from_false_to_true(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new ToggleArticleFavoriteDTO(
            userId: $userId,
            articleId: $articleId
        );

        $existingInteraction = new ArticleInteraction;
        $existingInteraction->id = 'interaction-789';
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->is_favorite = false;

        $updatedInteraction = clone $existingInteraction;
        $updatedInteraction->is_favorite = true;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        $this->articleInteractionService
            ->shouldReceive('update')
            ->once()
            ->with($existingInteraction, ['is_favorite' => true])
            ->andReturn($updatedInteraction);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(ArticleInteraction::class, $result);
        $this->assertTrue($result->is_favorite);
    }

    #[Test]
    public function it_handles_interaction_with_existing_reaction(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new ToggleArticleFavoriteDTO(
            userId: $userId,
            articleId: $articleId
        );

        $existingInteraction = new ArticleInteraction;
        $existingInteraction->id = 'interaction-789';
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->is_favorite = false;
        $existingInteraction->reaction = 'like';

        $updatedInteraction = clone $existingInteraction;
        $updatedInteraction->is_favorite = true;
        $updatedInteraction->reaction = 'like'; // Reaction should remain unchanged

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        $this->articleInteractionService
            ->shouldReceive('update')
            ->once()
            ->with($existingInteraction, ['is_favorite' => true])
            ->andReturn($updatedInteraction);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(ArticleInteraction::class, $result);
        $this->assertTrue($result->is_favorite);
        $this->assertEquals('like', $result->reaction);
    }

    #[Test]
    public function it_handles_transaction_rollback_on_failure(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new ToggleArticleFavoriteDTO(
            userId: $userId,
            articleId: $articleId
        );

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                $this->articleInteractionService
                    ->shouldReceive('findByUserAndArticle')
                    ->once()
                    ->andThrow(new Exception('Database error'));

                return $callback();
            });

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->action->execute($dto);
    }
}
