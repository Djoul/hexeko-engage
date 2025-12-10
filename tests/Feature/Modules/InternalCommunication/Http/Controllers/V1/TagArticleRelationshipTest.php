<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Enums\Security\AuthorizationMode;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\Tag;
use App\Models\Financer;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]

#[Group('tag')]
#[Group('article')]
class TagArticleRelationshipTest extends ProtectedRouteTestCase
{
    protected string $route = 'articles.update';

    protected string $permission = 'update_article';

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser(RoleDefaults::HEXEKO_SUPER_ADMIN, withContext: true, returnDetails: true);

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $this->currentFinancer->id);
    }

    #[Test]
    public function it_can_associate_tags_with_an_article(): void
    {
        // Arrange - use existing financer from setUp
        $author = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);

        // Create an article
        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $this->currentFinancer->id,
            'author_id' => $author->id,
        ]);

        // Create tags
        $tag1 = Tag::create([
            'financer_id' => $this->currentFinancer->id,
            'label' => ['en' => 'Tag 1', 'fr' => 'Tag 1', 'nl' => 'Tag 1'],
        ]);

        $tag2 = Tag::create([
            'financer_id' => $this->currentFinancer->id,
            'label' => ['en' => 'Tag 2', 'fr' => 'Tag 2', 'nl' => 'Tag 2'],
        ]);

        // Act
        $article->tags()->attach([$tag1->id, $tag2->id]);

        // Refresh the article from the database
        $article->load('tags');
        // Assert
        $this->assertCount(2, $article->tags);
        $this->assertTrue($article->tags->contains($tag1));
        $this->assertTrue($article->tags->contains($tag2));
    }

    #[Test]
    public function it_can_update_article_tags_via_api(): void
    {
        // Arrange - use existing financer from setUp
        $author = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);

        // Create an article
        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $this->currentFinancer->id,
            'author_id' => $author->id,
        ]);

        // Create tags
        $tag1 = Tag::create([
            'financer_id' => $this->currentFinancer->id,
            'label' => ['en' => 'Tag 1', 'fr' => 'Tag 1', 'nl' => 'Tag 1'],
        ]);

        $tag2 = Tag::create([
            'financer_id' => $this->currentFinancer->id,
            'label' => ['en' => 'Tag 2', 'fr' => 'Tag 2', 'nl' => 'Tag 2'],
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, [
                'financer_id' => $this->currentFinancer->id,
                'author_id' => $author->id,
                'title' => 'Updated Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'This is an updated article content'],
                            ],
                        ],
                    ],
                ],
                'tags' => [$tag1->id, $tag2->id],
                'language' => Languages::FRENCH_BELGIUM,
            ]);

        // Assert
        $response->assertStatus(200);

        // Refresh the article from the database
        $article->refresh();
        $article->load('tags');

        // Check that the tags are associated with the article
        $this->assertCount(2, $article->tags);
        $this->assertTrue($article->tags->contains($tag1));
        $this->assertTrue($article->tags->contains($tag2));
    }

    #[Test]
    public function it_validates_that_tags_belong_to_same_financer_as_article(): void
    {
        // Arrange - use existing financer from setUp as financer1
        $author = ModelFactory::createUser([
            'financers' => [['financer' => $this->currentFinancer]],
        ]);

        // Create an article for currentFinancer
        /**
         * @var Article $article
         *
         * @phpstan-var Article $article
         */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $this->currentFinancer->id,
            'author_id' => $author->id,
        ]);

        // Create a tag for a different financer
        $financer2 = Financer::factory()->create();

        // Hydrate context temporarily for financer2 to create the tag
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer2->id],
            [$financer2->division_id],
            [],
            $financer2->id  // Set current financer for global scopes
        );

        $tag = Tag::create([
            'financer_id' => $financer2->id,
            'label' => ['en' => 'Tag 1', 'fr' => 'Tag 1', 'nl' => 'Tag 1'],
        ]);

        // Restore context to currentFinancer
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->currentFinancer->id],
            [$this->currentFinancer->division_id],
            [],
            $this->currentFinancer->id  // Set current financer for global scopes
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson('/api/v1/internal-communication/articles/'.$article->id, [
                'financer_id' => $this->currentFinancer->id,
                'author_id' => $author->id,
                'title' => 'Updated Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        [
                            'type' => 'paragraph',
                            'content' => [
                                ['type' => 'text', 'text' => 'This is an updated article content'],
                            ],
                        ],
                    ],
                ],
                'tags' => [$tag->id],
                'language' => Languages::FRENCH_BELGIUM,
            ]);

        // Assert - Laravel validates array elements individually, so error key is 'tags.0'
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['tags.0']);
    }
}
