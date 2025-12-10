<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Actions\UpdateArticleReactionAction;
use App\Integrations\InternalCommunication\DTOs\UpdateArticleReactionDTO;
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

class UpdateArticleReactionActionTest extends TestCase
{
    private UpdateArticleReactionAction $action;

    private MockInterface $articleInteractionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->articleInteractionService = Mockery::mock(ArticleInteractionService::class);
        $this->action = new UpdateArticleReactionAction($this->articleInteractionService);
    }

    #[Test]
    public function it_creates_new_interaction_when_none_exists(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';
        $reaction = 'like';

        $dto = new UpdateArticleReactionDTO(
            userId: $userId,
            articleId: $articleId,
            reaction: $reaction
        );

        $expectedInteraction = new ArticleInteraction;
        $expectedInteraction->id = 'interaction-789';
        $expectedInteraction->user_id = $userId;
        $expectedInteraction->article_id = $articleId;
        $expectedInteraction->reaction = $reaction;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn(null);

        $this->articleInteractionService
            ->shouldReceive('create')
            ->once()
            ->with(Mockery::on(function (array $data) use ($userId, $articleId, $reaction): bool {
                return $data['user_id'] === $userId
                    && $data['article_id'] === $articleId
                    && $data['reaction'] === $reaction
                    && array_key_exists('article_translation_id', $data);

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
        $this->assertEquals($reaction, $result->reaction);
    }

    #[Test]
    public function it_updates_existing_interaction_with_new_reaction(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';
        $oldReaction = 'like';
        $newReaction = 'love';

        $dto = new UpdateArticleReactionDTO(
            userId: $userId,
            articleId: $articleId,
            reaction: $newReaction
        );

        $existingInteraction = new ArticleInteraction;
        $existingInteraction->id = 'interaction-789';
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->reaction = $oldReaction;

        $updatedInteraction = clone $existingInteraction;
        $updatedInteraction->reaction = $newReaction;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        $this->articleInteractionService
            ->shouldReceive('update')
            ->once()
            ->withAnyArgs()
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
        $this->assertEquals($newReaction, $result->reaction);
    }

    #[Test]
    public function it_keeps_reaction_when_same_reaction_is_applied(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';
        $reaction = 'like';

        $dto = new UpdateArticleReactionDTO(
            userId: $userId,
            articleId: $articleId,
            reaction: $reaction
        );

        $existingInteraction = new ArticleInteraction;
        $existingInteraction->id = 'interaction-789';
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->reaction = $reaction;

        $updatedInteraction = clone $existingInteraction;
        // Same reaction stays the same
        $updatedInteraction->reaction = $reaction;

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        $this->articleInteractionService
            ->shouldReceive('update')
            ->once()
            ->withAnyArgs()
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
        $this->assertEquals($reaction, $result->reaction);
    }

    #[Test]
    public function it_removes_reaction_when_null_is_passed(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';

        $dto = new UpdateArticleReactionDTO(
            userId: $userId,
            articleId: $articleId,
            reaction: null
        );

        $existingInteraction = Mockery::mock(ArticleInteraction::class);
        $existingInteraction->shouldReceive('setAttribute')->withAnyArgs();
        $existingInteraction->shouldReceive('getAttribute')
            ->with('user_id')->andReturn($userId);
        $existingInteraction->shouldReceive('getAttribute')
            ->with('article_id')->andReturn($articleId);
        $existingInteraction->shouldReceive('getAttribute')
            ->with('reaction')->andReturn('like');
        $existingInteraction->user_id = $userId;
        $existingInteraction->article_id = $articleId;
        $existingInteraction->reaction = 'like';

        $this->articleInteractionService
            ->shouldReceive('findByUserAndArticle')
            ->once()
            ->with($userId, $articleId)
            ->andReturn($existingInteraction);

        // Expect delete to be called on the interaction
        $existingInteraction
            ->shouldReceive('delete')
            ->once()
            ->andReturn(true);

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                return $callback();
            });

        // Act
        $result = $this->action->execute($dto);

        // Assert
        $this->assertInstanceOf(ArticleInteraction::class, $result);
        // The result should be a new empty ArticleInteraction instance
        $this->assertNull($result->id);
        $this->assertNull($result->reaction);
    }

    #[Test]
    public function it_handles_transaction_rollback_on_failure(): void
    {
        // Arrange
        $userId = 'user-123';
        $articleId = 'article-456';
        $reaction = 'like';

        $dto = new UpdateArticleReactionDTO(
            userId: $userId,
            articleId: $articleId,
            reaction: $reaction
        );

        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(function ($callback) {
                // Set up the service mock inside the transaction callback
                $this->articleInteractionService
                    ->shouldReceive('findByUserAndArticle')
                    ->once()
                    ->andThrow(new Exception('Database error'));

                // Call the callback which will trigger the exception
                return $callback();
            });

        // Act & Assert
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Database error');

        $this->action->execute($dto);
    }
}
