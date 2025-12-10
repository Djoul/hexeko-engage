<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Enums\ReactionTypeEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['articles'], scope: 'test')]
#[Group('internal-communication')]
#[Group('article')]
#[Group('interaction')]
class ArticleInteractionTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Set the active financer in Context for global scopes
        $financer = $this->auth->financers->first();
        if ($financer) {
            Context::add('financer_id', $financer->id);
        }
    }

    #[Test]
    public function authenticated_user_can_react_to_an_article(): void
    {
        $financer = $this->auth->financers->first();
        $initialCount = Article::count();
        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);
        $this->assertCount($initialCount + 1, Article::get());

        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/interactions", [
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'reaction' => ReactionTypeEnum::LIKE,
            'reaction_emoji' => ReactionTypeEnum::emoji(ReactionTypeEnum::LIKE),
        ]);

        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'reaction' => ReactionTypeEnum::LIKE,
        ]);
    }

    #[Test]
    public function authenticated_user_can_toggle_favorite_status(): void
    {

        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        // Mark as favorite
        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/toggle-favorite");

        $response->assertOk();
        $response->assertJsonFragment([
            'is_favorite' => true,
        ]);

        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'is_favorite' => true,
        ]);

        // Unmark as favorite
        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/toggle-favorite");

        $response->assertOk();
        $response->assertJsonFragment([
            'is_favorite' => false,
        ]);

        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'is_favorite' => false,
        ]);
    }

    #[Test]
    public function authenticated_user_can_update_reaction(): void
    {

        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        // First reaction
        $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/interactions", [
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        // Update reaction
        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/interactions", [
            'reaction' => ReactionTypeEnum::LOVE,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'reaction' => ReactionTypeEnum::LOVE,
            'reaction_emoji' => ReactionTypeEnum::emoji(ReactionTypeEnum::LOVE),
        ]);

        // Verify only one record exists
        $this->assertDatabaseCount('int_communication_rh_article_interactions', 1);
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'reaction' => ReactionTypeEnum::LOVE,
        ]);
    }

    #[Test]
    public function authenticated_user_can_get_their_interaction_with_an_article(): void
    {

        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        // Create interaction
        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'reaction' => ReactionTypeEnum::LAUGH,
            'is_favorite' => true,
        ]);

        $response = $this->actingAs($this->auth)->getJson("/api/v1/internal-communication/articles/{$article->id}/interactions");

        $response->assertOk();
        $response->assertJsonFragment([
            'reaction' => ReactionTypeEnum::LAUGH,
            'reaction_emoji' => ReactionTypeEnum::emoji(ReactionTypeEnum::LAUGH),
            'is_favorite' => true,
        ]);
    }

    #[Test]
    public function can_filter_articles_by_reaction_type(): void
    {
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = $this->auth->financers->first();

        // Update Context for new user's financer
        Context::add('financer_id', $financer->id);

        $initialCount = Article::count();

        // Create articles
        /** @var Article $article1 */
        $article1 = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        /** @var Article $article2 */
        $article2 = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Create interactions
        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $article1->id,
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $article2->id,
            'reaction' => ReactionTypeEnum::LOVE,
        ]);

        $this->assertDatabaseCount('int_communication_rh_article_interactions', 2);
        $this->assertEquals($initialCount + 3, Article::count());

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Filter by reaction type
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/internal-communication/articles?financer_id='.$financer->id.'&reaction_type='.ReactionTypeEnum::LIKE);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $article1->id,
        ]);
    }

    #[Test]
    public function can_filter_articles_by_favorite(): void
    {
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = $this->auth->financers->first();

        // Update Context for new user's financer
        Context::add('financer_id', $financer->id);

        // Create articles
        /** @var Article $article1 */
        $article1 = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        /** @var Article $article2 */
        $article2 = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        // Create interactions
        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $article1->id,
            'is_favorite' => true,
        ]);

        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $article2->id,
            'is_favorite' => false,
        ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Filter by favorite
        $response = $this->actingAs($this->auth)->getJson('/api/v1/internal-communication/articles?is_favorite=true&user_id='.$this->auth->id.'&financer_id='.$financer->id);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonFragment([
            'id' => $article1->id,
        ]);
    }

    #[Test]
    public function authenticated_user_can_remove_reaction_from_article(): void
    {

        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);

        // First, add a reaction
        $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/interactions", [
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        // Verify reaction exists
        $this->assertDatabaseHas('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        // Remove reaction by submitting null
        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$article->id}/interactions", [
            'reaction' => null,
        ]);

        $response->assertOk();
        $response->assertJsonFragment([
            'reaction' => null,
            'reaction_emoji' => null,
        ]);

        // Verify interaction record is completely deleted when reaction is null
        $this->assertDatabaseMissing('int_communication_rh_article_interactions', [
            'user_id' => $this->auth->id,
            'article_id' => $article->id,
        ]);
    }

    #[Test]
    public function it_returns_404_when_reacting_to_non_existent_article(): void
    {

        $nonExistentArticleId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$nonExistentArticleId}/interactions", [
            'reaction' => ReactionTypeEnum::LIKE,
        ]);

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);
    }

    #[Test]
    public function it_returns_404_when_toggling_favorite_on_non_existent_article(): void
    {

        $nonExistentArticleId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$nonExistentArticleId}/toggle-favorite");

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);
    }

    #[Test]
    public function it_returns_404_when_getting_interaction_for_non_existent_article(): void
    {

        $nonExistentArticleId = '00000000-0000-0000-0000-000000000000';

        $response = $this->actingAs($this->auth)->getJson("/api/v1/internal-communication/articles/{$nonExistentArticleId}/interactions");

        $response->assertNotFound();
        $response->assertJson(['error' => 'Article not found']);
    }
}
