<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\Me;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Models\Segment;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['articles'], scope: 'test')]
#[Group('internal-communication')]
#[Group('article')]
#[Group('me')]
class MeArticleIndexTest extends ProtectedRouteTestCase
{
    protected string $route = 'me.internal-communication.articles.index';

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(
            role: RoleDefaults::BENEFICIARY,
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
    public function it_lists_only_published_articles(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        // Create published articles
        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(3)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Create draft articles (should not appear)
        resolve(ArticleFactory::class)
            ->unpublished()
            ->withTranslations([$this->auth->locale => ['status' => StatusArticleEnum::DRAFT]])
            ->count(2)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('me.internal-communication.articles.index', ['financer_id' => $financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_by_segment(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Segment $segment */
        $segment = Segment::factory()->create([
            'financer_id' => $financer->id,
        ]);

        // Assign segment to user via many-to-many relation
        $this->auth->segments()->attach($segment->id);
        $this->auth->refresh();

        // Create articles in user's segment
        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(2)
            ->create([
                'financer_id' => $financer->id,
                'segment_id' => $segment->id,
                'author_id' => $this->auth->id,
            ]);

        // Create articles in different segment (should not appear)
        /** @var Segment $otherSegment */
        $otherSegment = Segment::factory()->create([
            'financer_id' => $financer->id,
        ]);

        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(3)
            ->create([
                'financer_id' => $financer->id,
                'segment_id' => $otherSegment->id,
                'author_id' => $this->auth->id,
            ]);

        // Create articles without segment (should appear - visible to all)
        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(1)
            ->create([
                'financer_id' => $financer->id,
                'segment_id' => null,
                'author_id' => $this->auth->id,
            ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('me.internal-communication.articles.index', ['financer_id' => $financer->id]));

        // Assert - Should see 2 (from own segment) + 1 (without segment) = 3 articles
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_filters_by_favorite_status(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Article $favoriteArticle */
        $favoriteArticle = resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Mark as favorite
        ArticleInteraction::create([
            'user_id' => $this->auth->id,
            'article_id' => $favoriteArticle->id,
            'is_favorite' => true,
        ]);

        // Create non-favorite articles
        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(2)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act - Filter by favorites
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles?is_favorite=1&financer_id={$financer->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $favoriteArticle->id);
    }

    #[Test]
    public function it_supports_pagination(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->count(25)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act - Request first page with 10 items
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles?per_page=10&page=1&financer_id={$financer->id}");

        // Assert - Controller returns collection without meta, so just verify item count
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data');

        // Act - Request second page
        $responsePage2 = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles?per_page=10&page=2&financer_id={$financer->id}");

        // Assert - Second page should have 10 items
        $responsePage2->assertStatus(200)
            ->assertJsonCount(10, 'data');
    }

    #[Test]
    public function it_shows_only_published_translations(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Create additional draft translation (should not appear)
        $article->translations()->create([
            'language' => 'en-US',
            'title' => 'Draft Title',
            'content' => ['text' => 'Draft content'],
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('me.internal-communication.articles.index', ['financer_id' => $financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Translations are keyed by language (see ArticleResource::getTranslationsFormated)
        $translations = $response->json('data.0.translations');

        // Verify only 1 translation is returned (not 2)
        $this->assertCount(1, $translations);

        // Verify only published translation is returned (check by status, not by key)
        $publishedTranslations = collect($translations)->filter(fn ($t): bool => $t['status'] === StatusArticleEnum::PUBLISHED);
        $draftTranslations = collect($translations)->filter(fn ($t): bool => $t['status'] === StatusArticleEnum::DRAFT);

        $this->assertCount(1, $publishedTranslations);
        $this->assertCount(0, $draftTranslations);
    }

    #[Test]
    public function it_excludes_draft_only_articles_even_for_god_users(): void
    {
        // Arrange - Create GOD user (has VIEW_DRAFT_ARTICLE permission)
        $godUser = $this->createAuthUser(
            role: RoleDefaults::GOD,
            withContext: true,
            returnDetails: true
        );

        $financer = $godUser->financers->first();
        Context::add('financer_id', $financer->id);

        // Create published article (should appear)
        resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$godUser->locale => []])
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $godUser->id,
            ]);

        // Create article with ONLY draft translations (should NOT appear in /me/* endpoints)
        resolve(ArticleFactory::class)
            ->unpublished()
            ->withTranslations([$godUser->locale => ['status' => StatusArticleEnum::DRAFT]])
            ->count(2)
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $godUser->id,
            ]);

        setPermissionsTeamId($godUser->team_id);

        // Act
        $response = $this->actingAs($godUser)
            ->getJson(route('me.internal-communication.articles.index', ['financer_id' => $financer->id]));

        // Assert - Should only see the 1 published article, not the 2 draft-only articles
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // Verify returned article has all required fields (status, title, translations not null)
        $article = $response->json('data.0');
        $this->assertNotNull($article['status'], 'Article status should not be null');
        $this->assertNotNull($article['title'], 'Article title should not be null');
        $this->assertNotEmpty($article['translations'], 'Article translations should not be empty');
        $this->assertEquals(StatusArticleEnum::PUBLISHED, $article['status']);
    }
}
