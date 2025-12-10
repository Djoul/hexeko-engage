<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('internal-communication')]
#[Group('article')]
class ArticleReadTest extends ProtectedRouteTestCase
{
    protected string $route = 'articles.index';

    protected function setUp(): void
    {
        parent::setUp();
        $this->auth = $this->createAuthUser(withContext: true);

        // Set the active financer in Context for global scopes
        $financer = $this->auth->financers->first();
        if ($financer) {
            Context::add('financer_id', $financer->id);
        }
    }

    #[Test]
    public function it_can_list_articles(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();
        /** @var User $author */
        $author = User::factory()->create();

        // Create articles with translations in the user's language and published status
        resolve(ArticleFactory::class)->published()->withTranslations([$this->auth->locale => []])->count(3)->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/internal-communication/articles');

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'financer_id',
                        'author_id',
                        'title',
                        'content',
                        'status',
                        'tags',
                        'published_at',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_can_show_an_article(): void
    {
        // Arrange
        $financer = $this->auth->financers->first();
        /** @var User $author */
        $author = User::factory()->create();

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->withTranslations()->create([
            'financer_id' => $financer->id,
            'author_id' => $author->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson('/api/v1/internal-communication/articles/'.$article->id);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $article->id,
                    'financer_id' => $financer->id,
                    'author_id' => $author->id,
                ],
            ]);
    }
}
