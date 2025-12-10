<?php

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\TagFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Models\Financer;
use App\Models\User;
use Context;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]
class ArticleCreateTest extends ProtectedRouteTestCase
{
    protected string $route = 'articles.update';

    protected string $permission = 'create_article';

    protected function setUp(): void
    {
        parent::setUp();

        // Configure local disk for media library to avoid S3 dependencies in CI
        config(['media-library.disk_name' => 'local']);
        Storage::fake('local');

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN, withContext: true, returnDetails: true);
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_can_create_an_article(): void
    {

        $initialVersionCount = ArticleVersion::count();
        /** @var Financer $financer */
        $financer = $this->currentFinancer;
        /** @var User $author */
        $author = ModelFactory::createUser([
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                ],
            ],
        ]);
        $tags = [
            $tag1Id = resolve(TagFactory::class)->create([
                'financer_id' => $financer->id,
                'label' => [
                    App::currentLocale() => 'HR',
                ],
            ])?->id,
            $tag2Id = resolve(TagFactory::class)->create([
                'financer_id' => $financer->id,
                'label' => [
                    App::currentLocale() => 'Internal',
                ],
            ])?->id,
        ];

        $articleData = [
            'author_id' => $author->id,
            'title' => 'Test Article',
            'content' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [
                            ['type' => 'text', 'text' => 'This is a test article content'],
                        ],
                    ],
                ],
            ],
            'tags' => $tags,
            'language' => App::currentLocale(),
        ];

        // Create a fake image file
        $file = UploadedFile::fake()->image('illustration.jpg');
        $base64Illustration = 'data:image/jpeg;base64,'.base64_encode(file_get_contents($file->path()));

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/internal-communication/articles', array_merge($articleData, [
                'illustration' => $base64Illustration,
            ]));

        // Assert
        $response->assertCreated();

        $this->assertDatabaseHas('int_communication_rh_articles', [
            'financer_id' => $financer->id,
            'author_id' => $author->id,
            // Can't check array directly, check not null
        ]);

        $article = Article::whereHas('translations', fn ($query) => $query->where('title', 'Test Article'))->latest()->first();

        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $article->id,
            'title' => 'Test Article',
            // Can't check array directly, check not null
        ]);
        $this->assertNotNull($article);

        // Check that the article is created with the correct content
        $this->assertEquals(['type' => 'doc',
            'content' => [
                0 => [
                    'type' => 'paragraph',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => 'This is a test article content',
                        ],
                    ],
                ],
            ]], $article->translation()->content);

        // Check that the article is associated with the financer
        $this->assertEquals($financer->id, $article->financer_id);

        // Check that the article is associated with the author
        $this->assertEquals($author->id, $article->author_id);

        // Check that a version is created on article creation
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialVersionCount + 1);
        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'version_number' => 1,
        ]);
    }

    #[Test]
    public function it_validates_required_fields(): void
    {
        // Setup: Create a financer and associate the user with it
        $financer = Financer::factory()->create();
        $this->auth->financers()->attach($financer->id, [
            'active' => true,
            'role' => RoleDefaults::FINANCER_SUPER_ADMIN,
        ]);
        Context::add('accessible_financers', [$financer->id]);
        Context::add('financer_id', $financer->id);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.Uuid::uuid4()->toString(), []);

        // Assert - financer_id is NOT validated because it's auto-assigned by the controller
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title', 'content']);
    }
}
