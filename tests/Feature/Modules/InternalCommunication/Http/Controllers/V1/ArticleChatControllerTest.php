<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InternalCommunication\Http\Controllers\V1;

use App;
use App\AI\Clients\OpenAIStreamerClient;
use App\AI\Contracts\LLMClientInterface;
use App\AI\LLMRouterService;
use App\Enums\IDP\RoleDefaults;
use App\Estimators\AiTokenEstimator;
use App\Http\Middleware\CheckCreditQuotaMiddleware;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Database\factories\ArticleVersionFactory;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Models\Financer;
use Config;
use Generator;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('article')]
#[Group('internal-communication')]
class ArticleChatControllerTest extends ProtectedRouteTestCase
{
    #[Test]
    public function test_authenticated_rh_can_generate_new_article_with_prompt(): void
    {
        Config::set('openai.api_key', 'api-key-mock');
        $this->withoutMiddleware(CheckCreditQuotaMiddleware::class);

        $initialCount = ArticleVersion::count();
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialCount);

        $mockClient = new class(app(AiTokenEstimator::class)) extends OpenAIStreamerClient
        {
            public function __construct(AiTokenEstimator $tokenEstimator)
            {
                parent::__construct($tokenEstimator);
            }

            public function streamPrompt(array $prompt, array $params = []): Generator
            {
                // Simulate XML-like tagged response
                yield '<opening>';
                yield 'Employee engagement is a crucial topic for any organization.';
                yield '</opening>';
                yield "\n\n";
                yield '<title>';
                yield '# Understanding Employee Engagement';
                yield '</title>';
                yield "\n\n";
                yield '<content>';
                yield "## What is Employee Engagement?\n\n";
                yield 'Employee engagement refers to the emotional commitment employees have to their organization and its goals.';
                yield "\n\n## Key Benefits\n\n";
                yield "- Increased productivity\n";
                yield "- Better retention rates\n";
                yield '- Higher customer satisfaction';
                yield '</content>';
                yield "\n\n";
                yield '<closing>';
                yield 'Would you like me to add specific strategies for improving engagement in your organization?';
                yield '</closing>';
            }

            public function getName(): string
            {
                return 'OpenAI';
            }
        };

        $this->app->bind(LLMRouterService::class, fn (): LLMRouterService => new LLMRouterService([$mockClient]));
        $this->app->bind(LLMClientInterface::class, fn (): object => $mockClient);
        $this->app->bind(OpenAIStreamerClient::class, fn (): object => $mockClient);

        // Let the service create the article (getOrCreateArticle) - pass new UUID
        $financer = $this->auth->financers->first();

        // Vérifier que le financer existe
        $this->assertNotNull($financer, 'User must have an associated financer');

        // Generate new UUID for article - let service create it via getOrCreateArticle
        $newArticleId = Uuid::uuid4()->toString();

        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$newArticleId}/chat", [
            'financer_id' => $financer->id,
            'prompt' => 'What is employee engagement?',
            'language' => $financer->available_languages[0], // Use financer's available language
        ]);

        // Verify request succeeded
        $response->assertStatus(200);

        // Capture le flux streamé (StreamedResponse) avec un output buffer
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Verify streaming worked
        $this->assertNotEmpty($content);

        // Verify article was created by the service with the specified ID
        $this->assertDatabaseHas('int_communication_rh_articles', [
            'id' => $newArticleId,
            'financer_id' => $financer->id,
            'author_id' => $this->auth->id,
        ]);

        // Verify translation was created
        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $newArticleId,
            'language' => $financer->available_languages[0],
        ]);
    }

    #[Test]
    public function test_authenticated_rh_can_generate_article_with_prompt_for_existing_article(): void
    {
        Config::set('openai.api_key', 'api-key-mock');
        $this->withoutMiddleware(CheckCreditQuotaMiddleware::class);

        $financer = $this->auth->financers->first();

        // Vérifier que le financer existe
        $this->assertNotNull($financer, 'User must have an associated financer');

        /** @var Article $article */
        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
            'deleted_at' => null,
        ]);
        $article->translations()->create([
            'language' => App::currentLocale(),
            'title' => 'Test Article',
            'content' => json_encode([
                'type' => 'text',
                'content' => '',
            ]
            ),
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // S'assurer que l'article a bien été créé
        $this->assertNotNull($article->getKey());
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($article->financer_id);

        $mockClient = new class(app(AiTokenEstimator::class)) extends OpenAIStreamerClient
        {
            public function __construct(AiTokenEstimator $tokenEstimator)
            {
                parent::__construct($tokenEstimator);
            }

            public function streamPrompt(array $prompt, array $params = []): Generator
            {
                // Simulate XML-like tagged response for existing article modification
                yield '<opening>';
                yield 'I will help you improve this article.';
                yield '</opening>';
                yield "\n\n";
                yield '<title>';
                yield '# Updated Article Title';
                yield '</title>';
                yield "\n\n";
                yield '<content>';
                yield "## Updated Content\n\n";
                yield 'This is the modified content with improvements.';
                yield '</content>';
                yield "\n\n";
                yield '<closing>';
                yield 'Would you like me to add more details?';
                yield '</closing>';
            }

            public function getName(): string
            {
                return 'OpenAI';
            }
        };

        $this->app->bind(LLMRouterService::class, fn (): LLMRouterService => new LLMRouterService([$mockClient]));
        $this->app->bind(LLMClientInterface::class, fn (): object => $mockClient);
        $this->app->bind(OpenAIStreamerClient::class, fn (): object => $mockClient);

        $articleId = $article->getKey();
        $this->assertIsString($articleId, 'Article ID must be a string');

        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$articleId}/chat", [
            'financer_id' => $financer->id,
            'prompt' => 'What is employee engagement?',
            'language' => App::currentLocale(),
        ]);

        // Capture le flux streamé (StreamedResponse) avec un output buffer
        ob_start();
        $response->sendContent();
        ob_get_clean();

        // LLM request creation has been moved to acceptSelectedText method
        // We now need to call acceptSelectedText to create the LLM request

        // Get the article translation
        $translation = $article->translation(App::currentLocale());
        $this->assertNotNull($translation);

        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $articleId,
            'language' => App::currentLocale(),
            'title' => 'Test Article',
        ]);
    }

    #[Test]
    public function test_article_update_creates_new_version(): void
    {
        $initialCount = ArticleVersion::count();
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);
        $translation = $article->translations()->create([
            'language' => App::currentLocale(),
            'title' => 'Update Article',
            'content' => [
                'type' => 'doc',
                'content' => [['insert' => 'Contenu initial']],
            ],
            'status' => StatusArticleEnum::DRAFT,
        ]);

        resolve(ArticleVersionFactory::class)->create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'version_number' => 1,
            'content' => [
                'type' => 'doc',
                'content' => [['insert' => 'Contenu initial']],
            ],
        ]);

        $this->assertDatabaseHas('int_communication_rh_article_versions', [
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'version_number' => 1,
        ]);

        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialCount + 1);

        // Premier update - financer_id must be query param for route model binding
        $response = $this->actingAs($this->auth)->putJson(
            "/api/v1/internal-communication/articles/{$article->id}?financer_id={$financer->getKey()}",
            [
                'language' => App::currentLocale(),
                'title' => 'Update Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [['insert' => 'Contenu modifié']],
                ],
            ]
        );

        $response->assertOk();

        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialCount + 2);

        // Deuxième update - financer_id must be query param for route model binding
        $response = $this->actingAs($this->auth)->putJson(
            "/api/v1/internal-communication/articles/{$article->id}?financer_id={$financer->getKey()}",
            [
                'language' => App::currentLocale(),
                'title' => 'Update Article',
                'content' => [
                    'type' => 'doc',
                    'content' => [['insert' => 'Contenu modifié 2']],
                ],
            ]
        );
        $response->assertOk();
        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialCount + 3);

        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $article->id,
            'language' => App::currentLocale(),
            'title' => 'Update Article',
        ]);
    }

    #[Test]
    public function article_multiple_updates_increments_versions(): void
    {

        $initialCount = ArticleVersion::count();

        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer);

        $article = resolve(ArticleFactory::class)->create([
            'financer_id' => $financer->getKey(),
            'author_id' => $this->auth->id,
        ]);
        $translation = $article->translations()->create([
            'language' => App::currentLocale(),
            'title' => 'Multi Update Article',
            'content' => [
                'type' => 'doc',
                'content' => [['insert' => 'Contenu initial']],
            ],
        ]);
        resolve(ArticleVersionFactory::class)->create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'language' => App::currentLocale(),
            'version_number' => 1,
            'content' => json_encode([
                'type' => 'doc',
                'content' => [['insert' => 'Contenu initial']],
            ]),
        ]);

        $this->assertDatabaseCount('int_communication_rh_article_versions', $initialCount + 1);
        $beforeUpdateCount = ArticleVersion::count();
        $updates = [
            'Première modif',
            'Deuxième modif',
            'Troisième modif',
        ];
        foreach ($updates as $i => $content) {
            $response = $this->actingAs($this->auth)->putJson(
                "/api/v1/internal-communication/articles/{$article->id}?financer_id={$financer->getKey()}",
                [
                    'language' => App::currentLocale(),
                    'title' => 'Multi Update Article',
                    'content' => [
                        'type' => 'doc',
                        'content' => [['insert' => $content]],
                    ],
                ]
            );
            $response->assertOk();
            $this->assertDatabaseCount('int_communication_rh_article_versions', ($i + 1) + $beforeUpdateCount);
        }

        $this->assertDatabaseHas('int_communication_rh_article_translations', [
            'article_id' => $article->id,
            'language' => App::currentLocale(),
            'title' => 'Multi Update Article',
        ]);
    }

    #[Test]
    public function test_stream_without_tags_logs_error_but_continues(): void
    {
        Config::set('openai.api_key', 'api-key-mock');

        $this->withoutMiddleware(CheckCreditQuotaMiddleware::class);

        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer, 'User must have an associated financer');

        // Mock client that returns stream without proper XML tags
        $mockClient = new class(app(AiTokenEstimator::class)) extends OpenAIStreamerClient
        {
            public function __construct(AiTokenEstimator $tokenEstimator)
            {
                parent::__construct($tokenEstimator);
            }

            public function streamPrompt(array $prompt, array $params = []): Generator
            {
                // Stream without proper tags - should log error but not fail HTTP request
                yield 'Short content without XML tags';
            }

            public function getName(): string
            {
                return 'OpenAI';
            }
        };

        $this->app->bind(LLMRouterService::class, fn (): LLMRouterService => new LLMRouterService([$mockClient]));
        $this->app->bind(LLMClientInterface::class, fn (): object => $mockClient);
        $this->app->bind(OpenAIStreamerClient::class, fn (): object => $mockClient);

        // Generate new UUID - let service create the article
        $newArticleId = Uuid::uuid4()->toString();

        // Make the request - should succeed with 200 despite missing tags
        // The error is logged but streaming continues
        $response = $this->actingAs($this->auth)->putJson("/api/v1/internal-communication/articles/{$newArticleId}/chat", [
            'financer_id' => $financer->id,
            'prompt' => 'Generate content without tags',
            'language' => $financer->available_languages[0],
        ]);

        // Stream succeeds with 200, but errors are logged
        $response->assertStatus(200);

        // Capture the streamed content
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        // Content should be streamed even without proper tags
        $this->assertNotEmpty($content);
        $this->assertStringContainsString('Short content', $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(
            role: RoleDefaults::HEXEKO_SUPER_ADMIN,
            withContext: true,
            returnDetails: true
        );

        // Set the active financer in Context for global scopes
        $financer = $this->auth->financers->first();
        if ($financer) {
            Context::add('financer_id', $financer->id);
        }
    }
}
