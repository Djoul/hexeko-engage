<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App;
use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Database\factories\ArticleVersionFactory;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Models\User;
use Carbon\Carbon;
use Context;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]

class ArticleUpdateTest extends ProtectedRouteTestCase
{
    protected string $route = 'articles.update';

    protected string $permission = 'update_article';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Set the active financer in Context for global scopes
        $financer = $this->auth->financers->first();
        if ($financer) {
            \Illuminate\Support\Facades\Context::add('financer_id', $financer->id);
        }
    }

    #[Test]
    public function it_can_update_an_article(): void
    {
        $this->assertDatabaseCount('int_communication_rh_article_translations', $initialTranslationsCount = ArticleTranslation::count());
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialVersionCount = ArticleVersion::count());

        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        // Debug: verify user has permission
        $this->assertTrue($this->auth->hasPermissionTo(PermissionDefaults::UPDATE_ARTICLE), 'User should have UPDATE_ARTICLE permission');

        // Debug: verify Context is set correctly
        $this->assertEquals($financer->id, Context::get('financer_id'), 'Context financer_id should match user financer');

        // Debug: verify activeFinancerID() returns correct value
        $this->assertEquals($financer->id, activeFinancerID(), 'activeFinancerID() should return correct financer');

        // Debug: verify user has financers loaded
        $this->assertNotEmpty($this->auth->financers, 'User should have financers loaded');
        $this->assertEquals($financer->id, $this->auth->financers->first()->id, 'First financer should match');

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)
            ->for($financer, 'financer')
            ->withTranslations()
            ->create([
                'author_id' => $this->auth->id,
            ]);

        resolve(ArticleVersionFactory::class)
            ->forArticle($article)
            ->state([
                'language' => App::currentLocale(),
                'version_number' => 1,
                'content' => json_encode([
                    'type' => 'doc',
                    'content' => [['insert' => 'Contenu initial']],
                ]),
            ])
            ->create();

        // Debug: verify article was created with correct financer_id
        $this->assertDatabaseHas('int_communication_rh_articles', [
            'id' => $article->id,
            'financer_id' => $financer->id,
        ]);

        $updatedData = [
            'language' => App::currentLocale(),
            // financer_id is automatically assigned in controller, not sent by client
            'title' => 'Updated Title',
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Updated content'],
                        ],
                    ],
                ],
            ],
        ];

        // Check that a version exists before update
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialVersionCount + 1);
        $this->assertDatabaseCount('int_communication_rh_article_translations', $initialTranslationsCount + 1);
        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'article_translation_id' => $article->translations->first()->id,
            'version_number' => 1,
        ]);

        // Perform the update (pass financer_id via query param for activeFinancerID() to work during request)
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id.'?financer_id='.$financer->id, $updatedData)->assertOk();

        // Check that a new version is created after update
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialVersionCount + 2);

        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'article_translation_id' => $article->translations->first()->id,
            'version_number' => 2,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $article->id,
                    'financer_id' => $financer->id,
                    'author_id' => $this->auth->id,
                    'title' => 'Updated Title',
                    'content' => [
                        'type' => 'doc',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => 'Updated content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('int_communication_rh_articles', [
            'id' => $article->id,
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,

        ]);
        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $article->id,
            'title' => 'Updated Title',
            'content' => json_encode([
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Updated content'],
                        ],
                    ],
                ],
            ]),

        ]);
    }

    #[Test]
    public function it_validates_required_fields_for_update(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, []);

        // Assert
        // Note: financer_id is auto-assigned in controller, not validated
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }

    // Note: LLMRequest creation via PUT /articles is removed.
    // LLMRequests are now only created in ArticleChatController::generate()
    // via SaveLLMRequestAction during the streaming process.

    #[Test]
    public function it_can_update_article_illustration(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /** @var User $author */
        $author = User::factory()->create();

        // Create an article with an initial illustration
        $initialFile = UploadedFile::fake()->image('initial_illustration.jpg');

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        // Attach the initial illustration
        $article->addMedia($initialFile)->toMediaCollection('illustration');

        // Verify that the initial illustration is attached
        $this->assertTrue($article->getMedia('illustration')->isNotEmpty());
        $initialMediaCount = $article->getMedia('illustration')->count();
        $initialMediaId = $article->getMedia('illustration')->first()->id;

        // Prepare a new illustration
        $newFile = UploadedFile::fake()->image('new_illustration.jpg');
        $base64Illustration = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($newFile->path()));

        $updatedData = [
            'language' => App::currentLocale(),
            'financer_id' => $financer->id,
            'author_id' => $author->id,
            'title' => 'Updated Article Title',
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Updated content with new illustration'],
                        ],
                    ],
                ],
            ],
            'illustration' => $base64Illustration,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, $updatedData);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $article->id,
                    'title' => 'Updated Article Title',
                    'content' => [
                        'type' => 'doc',
                        'content' => [
                            [
                                'type' => 'paragraph',
                                'content' => [
                                    ['type' => 'text', 'text' => 'Updated content with new illustration'],
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        // Refresh the article instance
        $article->refresh();

        // Verify that the new illustration is attached
        $this->assertTrue($article->getMedia('illustration')->isNotEmpty());

        // Verify that the old illustration has been replaced
        $this->assertNotEquals($initialMediaId, $article->getMedia('illustration', function (Media $media): bool {
            return isset($media->custom_properties['active']) && $media->custom_properties['active'] === true;
        })->first()->id);

        // Verify that the number of media files has not increased (addition)
        $this->assertEquals($initialMediaCount + 1, $article->getMedia('illustration')->count());
    }

    #[Test]
    public function it_changes_active_illustration_when_version_number_provided(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        $translation = $article->translations()->first();

        // Create 3 different medias for the article
        $file1 = UploadedFile::fake()->image('red.jpg');
        $media1 = $article->addMedia($file1)
            ->withCustomProperties(['active' => true])
            ->toMediaCollection('illustration');

        $file2 = UploadedFile::fake()->image('green.jpg');
        $media2 = $article->addMedia($file2)
            ->withCustomProperties(['active' => false])
            ->toMediaCollection('illustration');

        $file3 = UploadedFile::fake()->image('blue.jpg');
        $media3 = $article->addMedia($file3)
            ->withCustomProperties(['active' => false])
            ->toMediaCollection('illustration');

        // Create versions with different illustrations
        ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'content' => 'Version 1 content',
            'version_number' => 1,
            'illustration_id' => $media1->id,
        ]);

        ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'content' => 'Version 2 content',
            'version_number' => 2,
            'illustration_id' => $media2->id,
        ]);

        ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'content' => 'Version 3 content',
            'version_number' => 3,
            'illustration_id' => $media3->id,
        ]);

        // Verify initial state - media1 is active
        $activeMedia = $article->getMedia('illustration')->where('custom_properties.active', true)->first();
        $this->assertEquals($media1->id, $activeMedia->id);

        // Act - Update article with illustration_version_number = 2
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, [
                'language' => App::currentLocale(),
                'financer_id' => $financer->id,
                'title' => 'Updated Title',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Updated content with new illustration'],
                            ],
                        ],
                    ],
                ],
                'illustration_version_number' => 2,
            ]);

        $response->assertStatus(200);

        // Assert - media2 should now be active
        $article->refresh();
        $article->load('media');

        $activeMedias = $article->getMedia('illustration')->where('custom_properties.active', true);
        $this->assertCount(1, $activeMedias, 'Only one media should be active');

        $newActiveMedia = $activeMedias->first();
        $this->assertEquals($media2->id, $newActiveMedia->id, 'Media 2 should be the active media');

        // Verify other medias are inactive
        $inactiveMedias = $article->getMedia('illustration')->where('custom_properties.active', false);
        $this->assertCount(2, $inactiveMedias, 'Two medias should be inactive');
    }

    #[Test]
    public function it_ensures_only_one_media_is_active_per_article_skip(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Create multiple medias - try to set multiple as active
        $file1 = UploadedFile::fake()->image('first.jpg');
        $article->addMedia($file1)
            ->withCustomProperties(['active' => true])
            ->toMediaCollection('illustration');

        $file2 = UploadedFile::fake()->image('second.jpg');
        $article->addMedia($file2)
            ->withCustomProperties(['active' => true]) // Also try to set as active
            ->toMediaCollection('illustration');

        // Act - Update article with new illustration
        $newFile = UploadedFile::fake()->image('new_illustration.jpg');
        $base64Illustration = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($newFile->path()));

        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, [
                'language' => App::currentLocale(),
                'financer_id' => $financer->id,
                'title' => 'Updated Title',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'Updated content with new illustration'],
                            ],
                        ],
                    ],
                ],
                'illustration' => $base64Illustration,
            ]);

        $response->assertStatus(200);

        // Assert - Only one media should be active
        $article->refresh();
        $article->load('media');

        $activeMedias = $article->getMedia('illustration')->where('custom_properties.active', true);
        $this->assertCount(1, $activeMedias, 'Only one media should be active at any time');
    }

    #[Test]
    public function it_updates_status_and_published_at_when_article_is_published(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Ensure the article translation has DRAFT status initially
        $translation = $article->translations()->where('language', App::currentLocale())->first();
        $this->assertNotNull($translation);
        $translation->status = StatusArticleEnum::DRAFT;
        $translation->save();

        // Verify initial state
        $this->assertNull($translation->published_at);
        $this->assertEquals(StatusArticleEnum::DRAFT, $translation->status);

        // Act - Update the article to PUBLISHED status
        $updatedData = [
            'language' => App::currentLocale(),
            'financer_id' => $financer->id,
            'title' => 'Published Article',
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'This is now published content'],
                        ],
                    ],
                ],
            ],
            'status' => StatusArticleEnum::PUBLISHED,
        ];

        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, $updatedData);

        // Assert
        $response->assertStatus(200);

        // Refresh the article and translation from the database
        $article->refresh();
        $translation->refresh();

        // Verify that the status is updated in the translation
        $this->assertEquals(StatusArticleEnum::PUBLISHED, $translation->status);

        // Verify that published_at is set when status is PUBLISHED
        $this->assertNotNull($translation->published_at);
        $this->assertInstanceOf(Carbon::class, $translation->published_at);

        // Verify that the published_at is recent (within the last minute)
        $this->assertTrue($translation->published_at->isAfter(now()->subMinute()));

        // Verify that the article translation is correctly linked
        $this->assertEquals($article->id, $translation->article_id);
    }

    #[Test]
    public function it_does_not_create_new_version_when_content_title_or_illustration_is_not_modified(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Create initial version
        $initialContent = [
            'type' => 'doc',
            'content' => [['insert' => 'Initial content']],
        ];

        $translation = $article->translations()->where('language', App::currentLocale())->first();
        $this->assertNotNull($translation);
        $translation->content = $initialContent;
        $translation->status = StatusArticleEnum::DRAFT;
        $initialTitle = $translation->title;
        $translation->save();

        resolve(ArticleVersionFactory::class)
            ->forArticle($article)
            ->state([
                'article_translation_id' => $translation->id,
                'language' => App::currentLocale(),
                'version_number' => 1,
                'content' => json_encode($initialContent),
            ])
            ->create();

        // Check that a version exists before update
        $versionCountBefore = ArticleVersion::count();
        $this->assertEquals($versionCountBefore, ArticleVersion::count());

        // Update with the same content but different title
        $updatedData = [
            'language' => App::currentLocale(),
            // financer_id is automatically assigned in controller via activeFinancerID(), not sent by client
            'title' => $initialTitle,
            'content' => $initialContent, // Same content as before
            'status' => StatusArticleEnum::DRAFT,
        ];

        $this->assertDatabaseCount('int_communication_rh_article_versions', $versionCountBefore);

        // Perform the update (pass financer_id via query param for activeFinancerID() to work during request)
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id.'?financer_id='.$financer->id, $updatedData);

        // Assert
        $response->assertStatus(200);

        // Check that no new version is created after update with same content
        $this->assertDatabaseCount('int_communication_rh_article_versions', $versionCountBefore);

        // Verify that the title was updated
        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $article->id,
            'title' => $initialTitle,
        ]);

        // Now update with different content
        $updatedData['content'] = [
            'type' => 'doc',
            'content' => [['insert' => 'Modified content']],
        ];

        // Perform the update (pass financer_id via query param for activeFinancerID() to work during request)
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id.'?financer_id='.$financer->id, $updatedData);

        // Assert
        $response->assertStatus(200);

        // Check that a new version is created after update with different content
        $this->assertDatabaseCount('int_communication_rh_article_versions', $versionCountBefore + 1);
    }

    #[Test]
    public function it_creates_version_when_article_illustration_is_updated(): void
    {
        // Use the financer from setUp
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);
        /** @var User $author */
        $author = User::factory()->create();

        // Create an article with an initial illustration
        $initialFile = UploadedFile::fake()->image('initial_illustration.jpg');

        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        // Attach the initial illustration
        $article->addMedia($initialFile)->toMediaCollection('illustration');

        // Create an initial version
        resolve(ArticleVersionFactory::class)
            ->forArticle($article)
            ->state([
                'language' => $this->auth->language,
                'version_number' => 1,
                'content' => json_encode([
                    'type' => 'doc',
                    'content' => [['insert' => 'Initial content']],
                ]),
                'illustration_id' => null, // No illustration initially
            ])
            ->create();

        // Get the initial version count (may vary based on factory configuration)
        $initialVersionCount = ArticleVersion::where('article_id', $article->id)->count();

        // Ensure we have at least one version
        $this->assertGreaterThanOrEqual(1, $initialVersionCount);
        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'version_number' => 1,
        ]);

        // Prepare a new illustration
        $newFile = UploadedFile::fake()->image('new_illustration.jpg');
        $base64Illustration = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($newFile->path()));

        $updatedData = [
            'language' => App::currentLocale(),
            'financer_id' => $financer->id,
            'author_id' => $author->id,
            'title' => 'Updated Article Title',
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'Updated content with new illustration'],
                        ],
                    ],
                ],
            ],
            'illustration' => $base64Illustration,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/internal-communication/articles/'.$article->id, $updatedData, [
                'X-HTTP-Method-Override' => 'PUT',
            ]);

        // Assert
        $response->assertStatus(200);

        // Refresh the article instance
        $article->refresh();

        // Verify that a new version is created after update
        $this->assertCount($initialVersionCount + 1, ArticleVersion::where('article_id', $article->id)->get());
        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'version_number' => $initialVersionCount + 1,
        ]);

        // Get the latest version
        $latestVersion = $article->versions()->orderBy('version_number', 'desc')->first();
        $this->assertNotNull($latestVersion);

        // Verify that the illustration_id is stored in the version
        $this->assertNotNull($latestVersion->illustration_id);

        // Get the media associated with the version
        $media = Media::find($latestVersion->illustration_id);
        $this->assertNotNull($media);

        // Verify that the illustration URL from the media matches the active illustration URL of the article
        $this->assertEquals($article->active_illustration_url, $media->getUrl());
    }
}
