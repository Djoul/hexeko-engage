<?php

namespace App\Console\Commands;

use App\Integrations\InternalCommunication\Actions\UpdateArticleAction;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Console\Command;

class TestArticleScenariosCommand extends Command
{
    protected $signature = 'test:article-scenarios {scenarios?*}';

    protected $description = 'Execute article version creation E2E test scenarios';

    private array $results = [];

    public function handle(): int
    {
        $division = Division::first();
        $financer = Financer::where('division_id', $division->id)->first();

        if (! $financer) {
            $this->error('❌ No financer found');

            return 1;
        }

        $user = User::first();

        if (! $user) {
            $this->error('❌ No user found');

            return 1;
        }

        $requestedScenarios = $this->argument('scenarios');
        $scenariosToRun = empty($requestedScenarios) ? [1, 2, 3, 4, 5, 6, 7, 8] : array_map('intval', $requestedScenarios);

        foreach ($scenariosToRun as $scenarioNum) {
            $method = 'scenario'.$scenarioNum;
            if (method_exists($this, $method)) {
                $this->{$method}($financer, $user);
            }
        }

        $this->displaySummary();

        return 0;
    }

    private function scenario1(Financer $financer, User $user): void
    {
        $this->info("\n========== SCENARIO 1: Génération Article Vide ==========");

        $article = Article::create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => '',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        $beforeCount = $article->versions()->count();
        $this->comment("Article ID: {$article->id} - Initial versions: {$beforeCount}");

        // TURN 1: 4-tag XML
        $this->updateArticle($article, $financer, 'Tout savoir sur les congés payés', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Introduction']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés sont un droit fondamental.']]],
        ], '<response><opening>Voici un article</opening><title>Tout savoir sur les congés payés</title><content><h2>Introduction</h2><p>Les congés payés sont un droit fondamental.</p></content><closing>Bonne lecture !</closing></response>');

        $afterT1 = $article->fresh()->versions()->count();
        $this->line("After TURN 1 (4-tag): {$afterT1} versions");

        // TURN 2: 2-tag XML
        $this->updateArticle($article, $financer, 'Tout savoir sur les congés payés', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Introduction']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés sont un droit fondamental.']]],
        ], '<response><opening>Merci !</opening><closing>N\'hésitez pas.</closing></response>');

        $afterT2 = $article->fresh()->versions()->count();
        $this->line("After TURN 2 (2-tag): {$afterT2} versions");

        $this->recordResult('Scenario 1: Génération Article Vide', 2, $beforeCount + 1, $afterT2);
    }

    private function scenario2(Financer $financer, User $user): void
    {
        $this->info("\n========== SCENARIO 2: Modification Incrémentale ==========");

        $article = Article::create(['financer_id' => $financer->id, 'author_id' => $user->id]);
        ArticleTranslation::create([
            'article_id' => $article->id,
            'language' => 'fr-FR',
            'title' => '',
            'content' => ['type' => 'doc', 'content' => []],
        ]);

        $beforeCount = $article->versions()->count();
        $this->comment("Article ID: {$article->id} - Initial versions: {$beforeCount}");

        // TURN 1: 4-tag
        $this->updateArticle($article, $financer, 'Les congés payés en France', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent de se reposer.']]],
        ], '<response><opening>Voici</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent de se reposer.</p></content><closing>Premier jet.</closing></response>');

        // TURN 2: 4-tag
        $this->updateArticle($article, $financer, 'Les congés payés en France', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France: 5 semaines par an.']]],
        ], '<response><opening>Enrichi</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent de se reposer tout en étant rémunérés.</p><h2>Durée</h2><p>En France: 5 semaines par an.</p></content><closing>Version complète.</closing></response>');

        // TURN 3: 2-tag
        $this->updateArticle($article, $financer, 'Les congés payés en France', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France: 5 semaines par an.']]],
        ], '<response><opening>Parfait !</opening><closing>Content que ça convienne.</closing></response>');

        // TURN 4: 4-tag
        $this->updateArticle($article, $financer, 'Les congés payés en France', [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France: 5 semaines par an.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Exemple']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => '30 jours ouvrables.']]],
        ], '<response><opening>Ajouté exemples</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent de se reposer tout en étant rémunérés.</p><h2>Durée</h2><p>En France: 5 semaines par an.</p><h2>Exemple</h2><p>30 jours ouvrables.</p></content><closing>Final !</closing></response>');

        $finalCount = $article->fresh()->versions()->count();
        $this->recordResult('Scenario 2: Modification Incrémentale', 4, $beforeCount + 3, $finalCount);
    }

    private function updateArticle(Article $article, Financer $financer, string $title, array $contentBlocks, ?string $llmResponse = null): void
    {
        $payload = [
            'language' => 'fr-FR',
            'title' => $title,
            'content' => ['type' => 'doc', 'content' => $contentBlocks],
            'llm_response' => $llmResponse,
            'financer_id' => $financer->id,
        ];

        $action = app(UpdateArticleAction::class);
        $action->handle($article, $payload);
    }

    private function recordResult(string $scenario, int $turns, int $expected, int $actual): void
    {
        $status = ($actual === $expected) ? '✅ PASS' : '❌ FAIL';
        $this->results[] = ['scenario' => $scenario, 'turns' => $turns, 'expected' => $expected, 'actual' => $actual, 'status' => $status];

        $this->line("\n===== RESULTS =====");
        $this->line("Expected: {$expected} versions");
        $this->line("Actual: {$actual} versions");
        $this->line("Status: {$status}");
    }

    private function displaySummary(): void
    {
        $this->newLine(2);
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║            SCENARIOS EXECUTION SUMMARY                     ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->newLine();

        foreach ($this->results as $result) {
            $this->line(sprintf(
                '%-40s | Turns: %2d | Expected: %2d | Actual: %2d | %s',
                $result['scenario'],
                $result['turns'],
                $result['expected'],
                $result['actual'],
                $result['status']
            ));
        }

        $totalPass = count(array_filter($this->results, fn (array $r): bool => $r['status'] === '✅ PASS'));
        $totalScenarios = count($this->results);

        $this->newLine();
        $this->line("Total: {$totalPass}/{$totalScenarios} scenarios passed");

        if ($totalPass === $totalScenarios) {
            $this->info('✅ ALL SCENARIOS PASSED!');
        } else {
            $this->error('❌ SOME SCENARIOS FAILED!');
        }
    }
}
