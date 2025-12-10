<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
class ArticleReactionsCountTest extends ProtectedRouteTestCase
{
    #[Test]
    public function it_counts_only_reactions_not_all_interactions(): void
    {
        // Arrange
        $author = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        // Create translation
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr',
            'title' => 'Test Article',
            'content' => ['body' => 'Test content'],
            'status' => 'published',
        ]);

        // Create 3 users with the same financer
        $user1 = ModelFactory::createUser(['financers' => [['financer' => $financer, 'active' => true]]]);
        $user2 = ModelFactory::createUser(['financers' => [['financer' => $financer, 'active' => true]]]);
        $user3 = ModelFactory::createUser(['financers' => [['financer' => $financer, 'active' => true]]]);

        // Create interactions:
        // User 1: Has a reaction (thumbs_up)
        ArticleInteraction::create([
            'user_id' => $user1->id,
            'article_id' => $article->id,
            'reaction' => 'thumbs_up',
            'is_favorite' => false,
        ]);

        // User 2: Only marked as favorite (no reaction)
        ArticleInteraction::create([
            'user_id' => $user2->id,
            'article_id' => $article->id,
            'reaction' => null,
            'is_favorite' => true,
        ]);

        // User 3: Has a reaction (heart)
        ArticleInteraction::create([
            'user_id' => $user3->id,
            'article_id' => $article->id,
            'reaction' => 'heart',
            'is_favorite' => false,
        ]);

        // Re-hydrate context to ensure Article scope uses correct financer_id
        $this->hydrateAuthorizationContext($author);

        // Act
        $articleWithInteractions = Article::with('interactions')->find($article->id);

        // Assert
        // Total interactions should be 3
        $this->assertEquals(3, $articleWithInteractions->interactions->count());

        // But reactions count should only be 2 (excluding the favorite-only interaction)
        $reactionsCount = $articleWithInteractions->interactions->whereNotNull('reaction')->count();
        $this->assertEquals(2, $reactionsCount);

        // Verify the actual reactions
        $reactions = $articleWithInteractions->interactions
            ->pluck('reaction')
            ->filter()
            ->values()
            ->toArray();

        $this->assertCount(2, $reactions);
        $this->assertContains('thumbs_up', $reactions);
        $this->assertContains('heart', $reactions);
    }

    #[Test]
    public function it_returns_consistent_reactions_count_in_api_response(): void
    {
        // Arrange
        $author = $this->createAuthUser(withContext: true, returnDetails: true);
        $financer = $this->currentFinancer;

        $user = ModelFactory::createUser(['financers' => [['financer' => $financer, 'active' => true]]]);

        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr',
            'title' => 'Test Article',
            'content' => ['body' => 'Test content'],
            'status' => 'published',
        ]);

        // Create mixed interactions
        for ($i = 1; $i <= 5; $i++) {
            $testUser = ModelFactory::createUser(['financers' => [['financer' => $financer, 'active' => true]]]);

            ArticleInteraction::create([
                'user_id' => $testUser->id,
                'article_id' => $article->id,
                // Only users 1, 3, and 5 have reactions
                'reaction' => in_array($i, [1, 3, 5]) ? 'thumbs_up' : null,
                // Users 2 and 4 only have favorites
                'is_favorite' => in_array($i, [2, 4]),
            ]);
        }

        // Re-hydrate context to ensure Article scope uses correct financer_id
        $this->hydrateAuthorizationContext($author);

        // Act - Get single article
        $response = $this->actingAs($user)
            ->getJson("/api/v1/internal-communication/articles/{$article->id}");

        // Assert
        $response->assertOk();
        $data = $response->json('data');

        // Should count only actual reactions (3), not all interactions (5)
        $this->assertEquals(3, $data['reactions_count'], 'Reactions count should only include interactions with non-null reactions');
        $this->assertCount(3, $data['reactions'], 'Reactions array should only contain non-null reactions');
    }
}
