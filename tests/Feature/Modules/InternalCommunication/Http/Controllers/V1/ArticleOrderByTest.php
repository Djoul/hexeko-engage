<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App;
use App\Enums\IDP\RoleDefaults;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use Artisan;
use Illuminate\Support\Facades\Context;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('article')]
#[Group('internal-communication')]
class ArticleOrderByTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Use FINANCER_ADMIN role to have VIEW_DRAFT_ARTICLE permission
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        Artisan::call('cache:clear');

        $financer = $this->auth->financers->first();

        // Set the active financer in Context for global scopes
        Context::add('financer_id', $financer->id);

        resolve(ArticleFactory::class)->withTranslations([
            App::currentLocale() => [
                'title' => 'Alpha',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Lorem ipsum dolor sit amet.']]],
                    ],
                ],
                'status' => 'draft',
                'published_at' => now()->subDays(2),
            ],
        ])->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
            'created_at' => now()->subDays(2),
        ]);

        resolve(ArticleFactory::class)->withTranslations([
            App::currentLocale() => [
                'title' => 'Bravo',
                'content' => [
                    'type' => 'doc',
                    'content' => [
                        ['type' => 'paragraph', 'content' => [['text' => 'Lorem ipsum dolor sit amet.']]],
                    ],
                ],
                'status' => 'published',
                'published_at' => now()->subDay(),
            ],
        ])->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
            'created_at' => now()->subDay(),
        ]);

        resolve(ArticleFactory::class)->withTranslations(
            [
                App::currentLocale() => [
                    'title' => 'Charlie',
                    'content' => [
                        'type' => 'doc',
                        'content' => [
                            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Lorem ipsum dolor sit amet.']]],
                        ],
                    ],
                    'status' => 'pending',
                    'published_at' => now(),
                ],
            ]
        )->create([
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
            'created_at' => now(),
        ]);
    }

    #[Test]
    public function it_sorts_by_title_ascending(): void
    {
        $response = $this->getArticles(['order-by' => 'translations.title']);
        $response->assertOk();

        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $titles);
    }

    #[Test]
    public function it_sorts_by_title_descending(): void
    {
        $response = $this->getArticles(['order-by-desc' => 'translations.title']);
        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $titles);
    }

    #[Test]
    public function it_sorts_by_published_at_ascending(): void
    {
        $response = $this->getArticles(['order-by' => 'translations.published_at']);
        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $titles);
    }

    #[Test]
    public function it_sorts_by_published_at_descending(): void
    {
        $response = $this->getArticles(['order-by-desc' => 'translations.published_at']);
        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $titles);
    }

    #[Test]
    public function it_prioritizes_order_by_desc_when_both_params_are_present(): void
    {
        $response = $this->getArticles(['order-by' => 'translations.title', 'order-by-desc' => 'translations.published_at']);
        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $titles);
    }

    #[Test]
    public function it_returns_422_when_invalid_field_provided(): void
    {
        $response = $this->getArticles(['order-by' => 'invalid_field']);
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Invalid sort field: invalid_field']);
    }

    #[Test]
    public function it_falls_back_to_default_sorting_when_no_params(): void
    {
        $response = $this->getArticles();
        $response->assertOk();
        $titles = array_column($response->json('data'), 'title');
        // Default is translations.published_at desc
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $titles);
    }

    private function getArticles(array $params = []): TestResponse
    {
        return $this->actingAs($this->auth)->getJson(route('articles.index', $params));
    }
}
