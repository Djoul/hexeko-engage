<?php

namespace Tests\Unit\Modules\InternalCommunication\Models;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

#[Group('unit')]
#[Group('internal-communication')]
#[Group('article')]

class ArticleVersionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function test_creates_article_version(): void
    {
        // Ensure article is created and saved properly
        $article = resolve(ArticleFactory::class)->create();
        Context::add('financer_id', $article->financer_id);

        // Verify article was created
        $this->assertNotNull($article->id, 'Article was not created properly');
        $this->assertDatabaseHas('int_communication_rh_articles', [
            'id' => $article->id,
        ]);

        // Create a translation for the article
        $translation = $article->translations()->create([
            'language' => 'en',
            'title' => 'Test Article',
            'content' => ['content' => 'Test content'],

        ]);

        // Create a media for the article (simulate an uploaded illustration)
        $file = UploadedFile::fake()->image('illustration.jpg');
        $media = $article->addMedia($file)
            ->withCustomProperties(['active' => true])
            ->toMediaCollection('illustration');

        $version = ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => 'en',
            'content' => 'Generated article content',
            'prompt' => 'Write about employee well-being.',
            'llm_response' => 'Generated article content',
            'version_number' => 1,
            'illustration_id' => $media->id,
        ]);

        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'id' => $version->id,
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => 'en',
            'version_number' => 1,
            'illustration_id' => $media->id,
        ]);

        $this->assertNotNull($version->article);
        $this->assertTrue($version->article->is($article));

        $this->assertNotNull($version->translation);
        $this->assertTrue($version->translation->is($translation));
    }

    #[Test]
    public function it_has_media_relationship(): void
    {
        $article = resolve(ArticleFactory::class)->create();
        Context::add('financer_id', $article->financer_id);

        // Verify article was created
        $this->assertNotNull($article->id, 'Article was not created properly');
        $this->assertDatabaseHas('int_communication_rh_articles', [
            'id' => $article->id,
        ]);

        $translation = $article->translations()->create([
            'language' => 'en',
            'title' => 'Test Article',
            'content' => ['content' => 'Test content'],
        ]);

        // Create a media for the article
        $file = UploadedFile::fake()->image('illustration2.jpg');
        $media = $article->addMedia($file)
            ->withCustomProperties(['active' => true])
            ->toMediaCollection('illustration');

        $version = ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => 'en',
            'content' => 'Generated article content',
            'prompt' => 'Write about employee well-being.',
            'llm_response' => 'Generated article content',
            'version_number' => 1,
            'illustration_id' => $media->id,
        ]);

        // Test the media relationship
        $this->assertNotNull($version->media);
        $this->assertInstanceOf(Media::class, $version->media);
        $this->assertEquals($media->id, $version->media->id);
        $this->assertStringContainsString('illustration2.jpg', $version->media->file_name);
    }
}
