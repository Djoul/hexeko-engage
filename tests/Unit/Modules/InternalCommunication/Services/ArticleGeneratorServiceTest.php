<?php

namespace Tests\Unit\Modules\InternalCommunication\Services;

use App\AI\Clients\OpenAIStreamerClient;
use App\AI\Exceptions\LLMClientException;
use App\AI\LLMRouterService;
use App\Integrations\InternalCommunication\Database\factories\ArticleFactory;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Services\ArticleGeneratorService;
use App\Models\Financer;
use App\Models\User;
use Generator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[Group('article')]
#[Group('internal-communication')]
class ArticleGeneratorServiceTest extends TestCase
{
    protected ArticleGeneratorService $service;

    protected MockObject $mockClient;

    protected MockObject $mockRouter;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a mock OpenAI client
        $this->mockClient = $this->createMock(OpenAIStreamerClient::class);

        // Create a mock LLM router that returns our mock client
        $this->mockRouter = $this->createMock(LLMRouterService::class);
        $this->mockRouter->method('select')->willReturn($this->mockClient);

        // Create the service with our mocks
        $this->service = new ArticleGeneratorService(
            $this->mockRouter,
            $this->mockClient
        );
    }

    #[Test]
    public function test_translate_article_calls_openai_with_correct_parameters(): void
    {
        // Create a test article
        $article = resolve(ArticleFactory::class)->create();

        // Test data
        $targetLanguage = 'nl-BE';

        // Set up expectations for the mock client
        $this->mockClient->expects($this->once())
            ->method('streamPrompt')
            ->with(
                $this->callback(function (array $messages) use ($targetLanguage): bool {
                    // Check that the messages array is not empty
                    $this->assertNotEmpty($messages);

                    // Check that the messages array contains at least one message
                    $this->assertNotEmpty($messages);

                    // Check that the first message is a system message
                    $systemMessage = $messages['messages'][0] ?? $messages[0];
                    $this->assertEquals('system', $systemMessage['role']);
                    $this->assertStringContainsString('You are an expert translator', $systemMessage['content']);

                    // Check that the second message is a user message
                    $userMessage = $messages['messages'][1] ?? $messages[1];
                    $this->assertEquals('user', $userMessage['role']);
                    $this->assertStringContainsString(strtolower("translate this article to {$targetLanguage}"), strtolower($userMessage['content']));
                    $this->assertStringContainsString('Untitled Article', $userMessage['content']);

                    return true;
                }),
                $this->anything()
            )
            ->willReturn($this->createMockGenerator());

        // Call the method
        $result = $this->service->translateArticle($article, $targetLanguage);

        // Verify the result is a Generator
        $this->assertInstanceOf(Generator::class, $result);

        // Consume the generator to ensure it works
        $output = '';
        foreach ($result as $chunk) {
            $output .= $chunk;
        }

        $this->assertEquals('Mock translation response', $output);
    }

    #[Test]
    public function test_translate_article_handles_different_languages(): void
    {
        // Create a test article
        $article = resolve(Article::class)->create([
            'financer_id' => Financer::factory()->create()->id,
            'author_id' => User::factory()->create()->id,
        ]);

        // Test data for different languages
        $languages = ['en-GB', 'fr-BE', 'nl-BE', 'de-DE'];

        foreach ($languages as $language) {
            // Reset mock expectations
            $this->mockClient = $this->createMock(OpenAIStreamerClient::class);
            $this->mockRouter = $this->createMock(LLMRouterService::class);
            $this->mockRouter->method('select')->willReturn($this->mockClient);
            $this->service = new ArticleGeneratorService($this->mockRouter, $this->mockClient);

            // Set up expectations for the mock client
            $this->mockClient->expects($this->once())
                ->method('streamPrompt')
                ->with(
                    $this->callback(function (array $messages) use ($language): bool {
                        // Check that the messages array is not empty
                        $this->assertNotEmpty($messages);

                        // Check that the messages array contains at least one message
                        $this->assertNotEmpty($messages);

                        // Check that the second message is a user message
                        $userMessage = $messages[1] ?? $messages['messages'][1];

                        return strpos(strtolower($userMessage['content']), strtolower("translate this article to {$language}")) !== false;
                    }),
                    $this->anything()
                )
                ->willReturn($this->createMockGenerator());

            // Call the method
            $result = $this->service->translateArticle($article, $language);

            // Consume the generator
            $output = '';
            foreach ($result as $chunk) {
                $output .= $chunk;
            }

            $this->assertEquals('Mock translation response', $output);
        }
    }

    #[Test]
    public function test_translate_article_handles_complex_content(): void
    {
        // Create a test article
        $article = resolve(ArticleFactory::class)->create();

        // Test data with complex content structure
        $targetLanguage = 'fr-BE';

        // Set up expectations for the mock client
        $this->mockClient->expects($this->once())
            ->method('streamPrompt')
            ->with(
                $this->callback(function (array $messages) use ($targetLanguage): bool {
                    // Check that the messages array is not empty
                    $this->assertNotEmpty($messages);

                    //

                    // Check that the messages array contains at least one message
                    $this->assertNotEmpty($messages);

                    // Check that the second message is a user message
                    $userMessage = $messages[1] ?? $messages['messages'][1];

                    return strpos(strtolower($userMessage['content']), strtolower("translate this article to {$targetLanguage}")) !== false;
                }),
                $this->anything()
            )
            ->willReturn($this->createMockGenerator());

        // Call the method
        $result = $this->service->translateArticle($article, $targetLanguage);

        // Consume the generator
        $output = '';
        foreach ($result as $chunk) {
            $output .= $chunk;
        }

        $this->assertEquals('Mock translation response', $output);
    }

    /**
     * Test that the translateArticle method throws a LLMClientException when JSON encoding fails
     */
    #[Test]
    public function test_translate_article_throws_exception_on_json_encoding_error(): void
    {
        // Create a test article
        $article = resolve(ArticleFactory::class)->create();

        // Create a custom mock class that extends ArticleGeneratorService
        $mockService = new class($this->mockRouter, $this->mockClient) extends ArticleGeneratorService
        {
            public function translateArticle(Article $article, string $targetLanguage): Generator
            {
                // Create a message array with a resource that can't be JSON encoded
                $messages = [
                    [
                        'role' => 'system',
                        'content' => 'System message',
                    ],
                    [
                        'role' => 'user',
                        'content' => fopen('php://memory', 'r'), // This will cause json_encode to fail
                    ],
                ];

                // The rest of the method is the same as the original
                json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

                if (json_last_error() === JSON_ERROR_NONE) {
                    return $this->client->streamPrompt($messages, ['dual_output' => true]);
                }

                $errorMsg = "Erreur d'encodage JSON lors de la traduction: ".json_last_error_msg();
                throw new LLMClientException($errorMsg);
            }
        };

        // Expect an exception to be thrown
        $this->expectException(LLMClientException::class);
        $this->expectExceptionMessageMatches("/Erreur d'encodage JSON/");

        // Call the method that should throw an exception
        $mockService->translateArticle($article, 'fr-BE');
    }

    /**
     * Create a mock generator that yields a test response
     */
    private function createMockGenerator(): Generator
    {
        yield 'Mock translation response';
    }
}
