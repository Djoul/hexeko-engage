<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\Me;

use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Database\factories\TagFactory;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleInteraction;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use App\Models\Segment;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]
#[Group('me')]
class MeArticleShowTest extends ProtectedRouteTestCase
{
    protected string $route = 'me.internal-communication.articles.show';

    protected Financer $financer;

    protected Tag $tag;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $this->auth = $this->createAuthUser(RoleDefaults::BENEFICIARY, withContext: true);

        $this->financer = $this->auth->financers()->first();

        // Set the active financer in Context for global scopes BEFORE creating data
        Context::add('financer_id', $this->financer->id);

        $this->tag = resolve(TagFactory::class)->create(['financer_id' => $this->financer->id]);
    }

    #[Test]
    public function it_only_shows_a_published_article(): void
    {
        // Arrange

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->create([
                'financer_id' => $this->financer->id,
                'author_id' => $this->auth->id,
            ]);
        $article->tags()->attach($this->tag);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(
                route(
                    'me.internal-communication.articles.show',
                    ['article' => $article, 'financer_id' => $this->financer->id]
                ));

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $article->id)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'financer_id',
                    'author_id',
                    'title',
                    'content',
                    'status',
                    'tags',
                    'translations',
                    'published_at',
                    'created_at',
                    'updated_at',
                ],
            ]);
    }

    #[Test]
    public function it_returns_404_for_draft_article(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)
            ->unpublished()
            ->withTranslations([$this->auth->locale => ['status' => StatusArticleEnum::DRAFT]])
            ->create([
                'financer_id' => $financer->id,
                'author_id' => $this->auth->id,
            ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(404)
            ->assertJson(['error' => 'Article not found']);
    }

    #[Test]
    public function it_returns_404_for_article_in_different_segment(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Segment $userSegment */
        $userSegment = Segment::factory()->create([
            'financer_id' => $financer->id,
        ]);

        /** @var Segment $articleSegment */
        $articleSegment = Segment::factory()->create([
            'financer_id' => $financer->id,
        ]);

        // Assign segment to user via many-to-many relation
        $this->auth->segments()->attach($userSegment->id);
        $this->auth->refresh();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->create([
                'financer_id' => $financer->id,
                'segment_id' => $articleSegment->id,
                'author_id' => $this->auth->id,
            ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(404)
            ->assertJson(['error' => 'Article not found']);
    }

    #[Test]
    public function it_allows_access_to_article_without_segment(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();

        /** @var Segment $userSegment */
        $userSegment = Segment::factory()->create([
            'financer_id' => $financer->id,
        ]);

        // Assign segment to user via many-to-many relation
        $this->auth->segments()->attach($userSegment->id);
        $this->auth->refresh();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)
            ->published()
            ->withTranslations([$this->auth->locale => []])
            ->create([
                'financer_id' => $financer->id,
                'segment_id' => null, // No segment = visible to all
                'author_id' => $this->auth->id,
            ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonPath('data.id', $article->id);
    }

    #[Test]
    public function it_creates_interaction_when_viewing_article(): void
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

        $this->assertEquals(0, ArticleInteraction::count());

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(200);

        $this->assertEquals(1, ArticleInteraction::count());

        $interaction = ArticleInteraction::first();
        $this->assertEquals($this->auth->id, $interaction->user_id);
        $this->assertEquals($article->id, $interaction->article_id);
        $this->assertFalse($interaction->is_favorite);
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

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(200);

        // Translations are keyed by language, so we need to check the structure
        $translations = $response->json('data.translations');

        // Verify only 1 translation is returned (published one)
        $this->assertCount(1, $translations);

        // Verify only published translation is present
        $publishedTranslations = collect($translations)->filter(fn ($t): bool => $t['status'] === StatusArticleEnum::PUBLISHED);
        $this->assertCount(1, $publishedTranslations);
    }

    #[Test]
    public function it_filters_media_to_show_only_illustrations(): void
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

        // Create temporary file for testing
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image_');
        file_put_contents($tempFile, 'fake image content');

        // Add illustration media with active custom property
        $article->addMedia($tempFile)
            ->preservingOriginal()
            ->withCustomProperties(['active' => true])
            ->toMediaCollection('illustration');

        // Add other media (should be filtered out)
        $article->addMedia($tempFile)
            ->preservingOriginal()
            ->toMediaCollection('other');

        // Clean up temp file
        @unlink($tempFile);

        // Set permissions team context (required for Spatie permissions in HTTP context)
        setPermissionsTeamId($this->auth->team_id);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson("/api/v1/me/internal-communication/articles/{$article->id}?financer_id={$financer->id}");

        // Assert
        $response->assertStatus(200);

        // Verify only illustration media is returned (controller filters media by collection_name)
        // The response should contain the illustration field, not the media array
        $this->assertNotEmpty($response->json('data.illustration'));
    }
}
