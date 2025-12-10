<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\InternalCommunication\Actions;

use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\Enums\StatusArticleEnum;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Integrations\InternalCommunication\Models\ArticleVersion;
use App\Integrations\InternalCommunication\Services\ArticleGeneratorService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('unit')]
#[Group('article')]
#[Group('internal-communication')]
class ArticleLLMManualModificationTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateArticleAction $updateAction;

    private ArticleGeneratorService $generatorService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateAction = resolve(UpdateArticleAction::class);
        $this->generatorService = resolve(ArticleGeneratorService::class);
    }

    #[Test]
    public function it_creates_version_when_user_manually_modifies_article(): void
    {
        // Arrange: Create article with initial content
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);
        $user = ModelFactory::createUser([
            'email' => 'author@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $article = Article::create([
            'id' => Uuid::uuid4()->toString(),
            'financer_id' => $financer->id,
            'author_id' => $user->id,
        ]);

        $initialContent = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Initial content'],
                    ],
                ],
            ],
        ];

        $translation = $article->translations()->create([
            'language' => 'en',
            'title' => 'Initial Title',
            'content' => $initialContent,
            'status' => StatusArticleEnum::DRAFT,
        ]);

        $initialVersionCount = ArticleVersion::where('article_id', $article->id)->count();

        // Act: Manually modify the article
        $modifiedContent = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Manually modified content'],
                    ],
                ],
            ],
        ];

        $this->updateAction->handle($article, [
            'language' => 'en',
            'title' => 'Modified Title',
            'content' => $modifiedContent,
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // Assert: Version was created for manual modification
        $newVersionCount = ArticleVersion::where('article_id', $article->id)->count();
        $this->assertEquals($initialVersionCount + 1, $newVersionCount, 'A version should be created when content is manually modified');

        // Assert: Version contains the modified content
        $latestVersion = ArticleVersion::where('article_id', $article->id)
            ->where('article_translation_id', $translation->id)
            ->latest()
            ->first();

        $this->assertInstanceOf(ArticleVersion::class, $latestVersion);
        $this->assertEquals('Modified Title', $latestVersion->title);
        $this->assertEquals($modifiedContent, $latestVersion->content);
    }

    #[Test]
    public function it_sends_current_content_to_llm_after_manual_modification(): void
    {
        // Arrange: Create article with initial content
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);
        $user = ModelFactory::createUser([
            'email' => 'author@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $article = Article::create([
            'id' => Uuid::uuid4()->toString(),
            'financer_id' => $financer->id,
            'author_id' => $user->id,
        ]);

        $initialContent = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Initial content'],
                    ],
                ],
            ],
        ];

        $translation = $article->translations()->create([
            'language' => 'en',
            'title' => 'Initial Title',
            'content' => $initialContent,
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // Create initial version
        ArticleVersion::create([
            'article_id' => $article->id,
            'article_translation_id' => $translation->id,
            'version_number' => 1,
            'title' => 'Initial Title',
            'content' => $initialContent,
            'language' => 'en',
            'author_id' => $user->id,
        ]);

        // Act: Manually modify the article (simulating user editing in frontend)
        $modifiedContent = [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => 'Manually modified content'],
                    ],
                ],
            ],
        ];

        $this->updateAction->handle($article, [
            'language' => 'en',
            'title' => 'Modified Title',
            'content' => $modifiedContent,
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // Refresh article to get latest data
        $article->refresh();

        // Act: Build LLM context messages
        $translation = $article->translations()->where('language', 'en')->first();
        $this->assertInstanceOf(ArticleTranslation::class, $translation);

        $messages = $this->generatorService->manageMessages(
            promptUser: 'Please improve this article',
            articleTranslation: $translation,
            language: 'en',
            withHistory: true
        );

        // Assert: Messages contain current modified content, not initial content
        $messagesJson = json_encode($messages);
        $this->assertIsString($messagesJson);

        // Should contain the manually modified content
        $this->assertStringContainsString('Manually modified content', $messagesJson, 'LLM context should contain the current manually modified content');

        // Should NOT contain the old initial content in the current_content section
        $currentContentMessages = array_filter($messages, function (array $message): bool {
            return array_key_exists('role', $message) && $message['role'] === 'assistant'
                && array_key_exists('content', $message) && str_contains($message['content'], '<current_content>');
        });

        $this->assertNotEmpty($currentContentMessages, 'There should be a current_content message in the LLM context');

        $currentContentMessage = array_values($currentContentMessages)[0]['content'] ?? '';
        $this->assertStringContainsString('Manually modified content', $currentContentMessage, 'Current content should reflect manual modifications');
        $this->assertStringNotContainsString('Initial content', $currentContentMessage, 'Current content should not contain old initial text');
    }

    #[Test]
    public function it_maintains_version_history_correctly(): void
    {
        // Arrange: Create article
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
        ]);
        $user = ModelFactory::createUser([
            'email' => 'author@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $article = Article::create([
            'id' => Uuid::uuid4()->toString(),
            'financer_id' => $financer->id,
            'author_id' => $user->id,
        ]);

        $article->translations()->create([
            'language' => 'en',
            'title' => 'Version 1',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Content V1']]]]],
            'status' => StatusArticleEnum::DRAFT,
        ]);

        $initialVersionCount = ArticleVersion::where('article_id', $article->id)->count();

        // Act: Make multiple manual modifications
        $this->updateAction->handle($article, [
            'language' => 'en',
            'title' => 'Version 2',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Content V2']]]]],
            'status' => StatusArticleEnum::DRAFT,
        ]);

        $this->updateAction->handle($article, [
            'language' => 'en',
            'title' => 'Version 3',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Content V3']]]]],
            'status' => StatusArticleEnum::DRAFT,
        ]);

        // Assert: 2 versions created
        $finalVersionCount = ArticleVersion::where('article_id', $article->id)->count();
        $this->assertEquals($initialVersionCount + 2, $finalVersionCount);

        // Assert: Version numbers increment correctly
        $versions = ArticleVersion::where('article_id', $article->id)
            ->orderBy('version_number')
            ->get();

        $versionNumbers = $versions->pluck('version_number')->toArray();
        $this->assertEquals([1, 2], $versionNumbers);

        // Assert: Each version has correct content
        $version1 = $versions[0];
        $version2 = $versions[1];
        $this->assertInstanceOf(ArticleVersion::class, $version1);
        $this->assertInstanceOf(ArticleVersion::class, $version2);
        $this->assertEquals('Version 2', $version1->title);
        $this->assertEquals('Version 3', $version2->title);
    }
}
