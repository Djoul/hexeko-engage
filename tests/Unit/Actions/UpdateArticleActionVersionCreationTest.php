<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

/**
 * Test version creation logic in UpdateArticleAction.
 */
#[Group('articles')]
#[Group('version-creation')]
class UpdateArticleActionVersionCreationTest extends TestCase
{
    use DatabaseTransactions;

    protected UpdateArticleAction $updateArticleAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->updateArticleAction = app(UpdateArticleAction::class);
    }

    /**
     * Test that conversational messages (2-tag XML) do NOT create versions.
     */
    #[Test]
    public function it_does_not_create_version_for_conversational_llm_response(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        $article = Article::factory()->create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        // Conversational response (2-tag XML) - only <opening> and <closing>
        $conversationalResponse = '<response><opening>Merci pour votre retour !</opening><closing>N\'hésitez pas si vous avez d\'autres questions.</closing></response>';

        $beforeCount = $article->versions()->count();

        // Call action directly (unit test)
        $this->updateArticleAction->handle($article, [
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
            'llm_response' => $conversationalResponse,
            'financer_id' => $financer->id,
        ]);

        $afterCount = $article->fresh()->versions()->count();

        $this->assertEquals($beforeCount, $afterCount, 'Conversational response (2-tag) should NOT create version');
    }

    /**
     * Test that article generation (4-tag XML) creates a version.
     */
    #[Test]
    public function it_creates_version_for_article_generation_response(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        $article = Article::factory()->create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        // Article generation response (4-tag XML) - has <title> and <content>
        $articleResponse = '<response><opening>Voici votre article</opening><title>Mon Titre</title><content><h2>Section 1</h2><p>Contenu de test...</p></content><closing>Bonne lecture !</closing></response>';

        $beforeCount = $article->versions()->count();

        // Call action directly (unit test)
        $this->updateArticleAction->handle($article, [
            'language' => 'fr-FR',
            'title' => 'Mon Titre',
            'content' => ['type' => 'doc', 'content' => [['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'Section 1']]]]],
            'llm_response' => $articleResponse,
            'financer_id' => $financer->id,
        ]);

        $afterCount = $article->fresh()->versions()->count();

        $this->assertEquals($beforeCount + 1, $afterCount, 'Article generation (4-tag) should create version');
    }

    /**
     * Test that manual modifications create a version.
     */
    #[Test]
    public function it_creates_version_for_manual_content_modification(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        $article = Article::factory()->create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        $beforeCount = $article->versions()->count();

        // Manual modification - no llm_response
        $this->updateArticleAction->handle($article, [
            'language' => 'fr-FR',
            'title' => 'Modified Title',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'New content']]]]],
            'financer_id' => $financer->id,
        ]);

        $afterCount = $article->fresh()->versions()->count();

        $this->assertEquals($beforeCount + 1, $afterCount, 'Manual modification should create version');
    }

    /**
     * Test that empty XML or malformed XML does NOT create version.
     */
    #[Test]
    public function it_does_not_create_version_for_malformed_llm_response(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser();

        $article = Article::factory()->create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        $beforeCount = $article->versions()->count();

        // Malformed XML
        $this->updateArticleAction->handle($article, [
            'language' => 'fr-FR',
            'title' => 'Test Article',
            'content' => ['type' => 'doc', 'content' => []],
            'llm_response' => '<invalid>not valid xml',
            'financer_id' => $financer->id,
        ]);

        $afterCount = $article->fresh()->versions()->count();

        $this->assertEquals($beforeCount, $afterCount, 'Malformed XML should NOT create version');
    }
}
