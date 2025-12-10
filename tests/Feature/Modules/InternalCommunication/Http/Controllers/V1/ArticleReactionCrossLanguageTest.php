<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]
#[Group('interaction')]

class ArticleReactionCrossLanguageTest extends ProtectedRouteTestCase
{
    // DatabaseTransactions is already included in ProtectedRouteTestCase

    private Article $article;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create a user with authorization context and capture financer details
        $this->user = $this->createAuthUser(withContext: true, returnDetails: true);

        // Set the active financer in Context for global scopes
        $financer = $this->user->financers->first();
        if ($financer) {
            Context::add('financer_id', $financer->id);
        }

        // Create an article with multiple translations for the user's financer
        $this->article = resolve(ArticleFactory::class)->withTranslations(['fr', 'en'])->create([
            'financer_id' => $financer->id,
            'author_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_shows_reaction_count_aggregated_across_all_translations(): void
    {
        // Given: A user reacts to the French version
        $frenchTranslation = $this->article->translations()->where('language', 'fr')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // When: We fetch the article in English
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/internal-communication/articles/{$this->article->id}?lang=en");

        // Then: The reaction count should be 1 (not 0)
        $response->assertOk()
            ->assertJsonPath('data.reactions_count', 1)
            ->assertJsonPath('data.reaction', 'like')
            ->assertJson([
                'data' => [
                    'reactions' => ['like'],
                ],
            ]);
    }

    #[Test]
    public function it_allows_only_one_reaction_per_user_across_all_translations(): void
    {
        // Given: A user reacts to the French version
        $frenchTranslation = $this->article->translations()->where('language', 'fr')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // When: The same user reacts differently to the English version
        $englishTranslation = $this->article->translations()->where('language', 'en')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'love',
                'article_translation_id' => $englishTranslation->id,
            ])
            ->assertOk();

        // Then: Only one reaction should exist in the database
        $this->assertDatabaseCount('int_communication_rh_article_interactions', 1);

        // And: The reaction should be the latest one (love)
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->user->id,
            'article_id' => $this->article->id,
            'reaction' => 'love',
            'article_translation_id' => $englishTranslation->id,
        ]);
    }

    #[Test]
    public function it_shows_same_reaction_count_in_all_translations(): void
    {
        // Given: Multiple users in the same financer react to different translations
        $user1 = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);
        $user2 = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);
        $user3 = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);

        $frenchTranslation = $this->article->translations()->where('language', 'fr')->first();
        $englishTranslation = $this->article->translations()->where('language', 'en')->first();

        // User 1 reacts to French version
        $this->actingAs($user1)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // User 2 reacts to English version
        $this->actingAs($user2)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'love',
                'article_translation_id' => $englishTranslation->id,
            ])
            ->assertOk();

        // User 3 reacts to French version
        $this->actingAs($user3)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'laugh',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // When: We fetch the article in French
        $frenchResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/internal-communication/articles/{$this->article->id}?lang=fr");

        // And: We fetch the article in English
        $englishResponse = $this->actingAs($this->user)
            ->getJson("/api/v1/internal-communication/articles/{$this->article->id}?lang=en");

        // Then: Both should show the same reaction count (3)
        $frenchResponse->assertJsonPath('data.reactions_count', 3);
        $englishResponse->assertJsonPath('data.reactions_count', 3);

        // And: Both should show all reactions (order doesn't matter)
        $frenchReactions = $frenchResponse->json('data.reactions');
        $englishReactions = $englishResponse->json('data.reactions');

        $this->assertEqualsCanonicalizing(['like', 'love', 'laugh'], $frenchReactions);
        $this->assertEqualsCanonicalizing(['like', 'love', 'laugh'], $englishReactions);
    }

    #[Test]
    public function it_removes_reaction_when_sending_null(): void
    {
        // Given: A user has reacted to an article
        $frenchTranslation = $this->article->translations()->where('language', 'fr')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // Verify the reaction exists
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->user->id,
            'article_id' => $this->article->id,
            'reaction' => 'like',
        ]);

        // When: The user sends null to remove the reaction
        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => null,
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // Then: The interaction should be deleted
        $this->assertDatabaseMissing('int_communication_rh_article_interactions', [
            'user_id' => $this->user->id,
            'article_id' => $this->article->id,
        ]);
    }

    #[Test]
    public function it_updates_article_translation_id_when_reacting_from_different_language(): void
    {
        // Given: A user reacts to the French version
        $frenchTranslation = $this->article->translations()->where('language', 'fr')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like',
                'article_translation_id' => $frenchTranslation->id,
            ])
            ->assertOk();

        // Verify the translation ID is set to French
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->user->id,
            'article_id' => $this->article->id,
            'article_translation_id' => $frenchTranslation->id,
        ]);

        // When: The same user changes language and keeps the same reaction
        $englishTranslation = $this->article->translations()->where('language', 'en')->first();

        $this->actingAs($this->user)
            ->putJson("/api/v1/internal-communication/articles/{$this->article->id}/interactions", [
                'reaction' => 'like', // Same reaction from different language
                'article_translation_id' => $englishTranslation->id,
            ])
            ->assertOk();

        // Then: The translation ID should be updated to English
        // And: The reaction should remain the same
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->user->id,
            'article_id' => $this->article->id,
            'article_translation_id' => $englishTranslation->id,
            'reaction' => 'like', // Reaction remains when updating from different language
        ]);
    }
}
