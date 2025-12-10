<?php

declare(strict_types=1);

use App\Integrations\InternalCommunication\Http\Controllers\ArticleController;
use App\Integrations\InternalCommunication\Models\Article;
use App\Integrations\InternalCommunication\Models\ArticleTranslation;
use App\Models\Division;
use App\Models\Financer;
use Illuminate\Http\Request;

// Helper function to simulate API request
function simulateArticleUpdate(int $articleId, int $financerId, string $title, array $content, ?string $llmResponse = null): void
{
    $request = Request::create('/api/v1/articles/'.$articleId, 'PUT', [
        'language' => 'fr-FR',
        'title' => $title,
        'content' => $content,
        'llm_response' => $llmResponse,
        'financer_id' => $financerId,
    ]);

    $controller = app(ArticleController::class);
    $controller->update($request, $articleId);
}

// Get test data
$division = Division::first();
$financer = Financer::where('division_id', $division->id)->first();

if (! $financer) {
    echo "❌ ERROR: No financer found\n";
    exit(1);
}

$allResults = [];

// ==================== SCENARIO 1 ====================
echo "\n========== SCENARIO 1: Génération Article Vide ==========\n";

$article1 = Article::create([
    'financer_id' => $financer->id,
    'status' => 'draft',
]);

$translation1 = ArticleTranslation::create([
    'article_id' => $article1->id,
    'language' => 'fr-FR',
    'title' => '',
    'content' => ['type' => 'doc', 'content' => []],
]);

$beforeCount1 = $article1->versions()->count();
echo "Article ID: {$article1->id} - Initial versions: {$beforeCount1}\n";

// TURN 1: Article generation (4-tag XML)
$llmResponse1T1 = '<response><opening>Voici un article sur les congés payés</opening><title>Tout savoir sur les congés payés</title><content><h2>Introduction</h2><p>Les congés payés sont un droit fondamental.</p></content><closing>Bonne lecture !</closing></response>';

simulateArticleUpdate(
    $article1->id,
    $financer->id,
    'Tout savoir sur les congés payés',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Introduction']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés sont un droit fondamental.']]],
        ],
    ],
    $llmResponse1T1
);

$afterTurn1 = $article1->fresh()->versions()->count();
echo "After TURN 1 (4-tag): {$afterTurn1} versions (expected: ".($beforeCount1 + 1).")\n";

// TURN 2: Conversational acknowledgment (2-tag XML)
$llmResponse1T2 = '<response><opening>Merci pour votre feedback !</opening><closing>N\'hésitez pas si vous avez besoin d\'autres modifications.</closing></response>';

simulateArticleUpdate(
    $article1->id,
    $financer->id,
    'Tout savoir sur les congés payés',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Introduction']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés sont un droit fondamental.']]],
        ],
    ],
    $llmResponse1T2
);

$afterTurn2 = $article1->fresh()->versions()->count();
echo "After TURN 2 (2-tag): {$afterTurn2} versions (expected: {$afterTurn1} - NO change)\n";

$expected1 = $beforeCount1 + 1;
$actual1 = $article1->fresh()->versions()->count();
$status1 = ($actual1 === $expected1) ? '✅ PASS' : '❌ FAIL';

echo "\n===== RESULTS =====\n";
echo "Expected: {$expected1} versions\n";
echo "Actual: {$actual1} versions\n";
echo "Status: {$status1}\n";

$allResults[] = [
    'scenario' => 'Scenario 1: Génération Article Vide',
    'turns' => 2,
    'expected' => $expected1,
    'actual' => $actual1,
    'status' => $status1,
];

// ==================== SCENARIO 2 ====================
echo "\n========== SCENARIO 2: Modification Incrémentale avec Feedback ==========\n";

$article2 = Article::create([
    'financer_id' => $financer->id,
    'status' => 'draft',
]);

$translation2 = ArticleTranslation::create([
    'article_id' => $article2->id,
    'language' => 'fr-FR',
    'title' => '',
    'content' => ['type' => 'doc', 'content' => []],
]);

$beforeCount2 = $article2->versions()->count();
echo "Article ID: {$article2->id} - Initial versions: {$beforeCount2}\n";

// TURN 1: Initial generation (4-tag)
$llmResponse2T1 = '<response><opening>Voici votre article</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent aux salariés de se reposer.</p></content><closing>Voilà un premier jet.</closing></response>';

simulateArticleUpdate(
    $article2->id,
    $financer->id,
    'Les congés payés en France',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent aux salariés de se reposer.']]],
        ],
    ],
    $llmResponse2T1
);

$afterT1 = $article2->fresh()->versions()->count();
echo "After TURN 1 (4-tag): {$afterT1} versions\n";

// TURN 2: Improved version (4-tag)
$llmResponse2T2 = '<response><opening>J\'ai enrichi le contenu</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent aux salariés de se reposer tout en étant rémunérés.</p><h2>Durée</h2><p>En France, chaque salarié a droit à 5 semaines de congés payés par an.</p></content><closing>Voici une version plus complète.</closing></response>';

simulateArticleUpdate(
    $article2->id,
    $financer->id,
    'Les congés payés en France',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent aux salariés de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France, chaque salarié a droit à 5 semaines de congés payés par an.']]],
        ],
    ],
    $llmResponse2T2
);

$afterT2 = $article2->fresh()->versions()->count();
echo "After TURN 2 (4-tag): {$afterT2} versions\n";

// TURN 3: Conversational (2-tag) - should NOT create version
$llmResponse2T3 = '<response><opening>Parfait comme ça !</opening><closing>Content que cela vous convienne.</closing></response>';

simulateArticleUpdate(
    $article2->id,
    $financer->id,
    'Les congés payés en France',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent aux salariés de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France, chaque salarié a droit à 5 semaines de congés payés par an.']]],
        ],
    ],
    $llmResponse2T3
);

$afterT3 = $article2->fresh()->versions()->count();
echo "After TURN 3 (2-tag): {$afterT3} versions (expected: {$afterT2} - NO change)\n";

// TURN 4: Final improvements (4-tag)
$llmResponse2T4 = '<response><opening>J\'ai ajouté des exemples</opening><title>Les congés payés en France</title><content><h2>Définition</h2><p>Les congés payés permettent aux salariés de se reposer tout en étant rémunérés.</p><h2>Durée</h2><p>En France, chaque salarié a droit à 5 semaines de congés payés par an.</p><h2>Exemple</h2><p>Pour un salarié à temps plein, cela représente 30 jours ouvrables de congés.</p></content><closing>Version finale !</closing></response>';

simulateArticleUpdate(
    $article2->id,
    $financer->id,
    'Les congés payés en France',
    [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Définition']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Les congés payés permettent aux salariés de se reposer tout en étant rémunérés.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Durée']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'En France, chaque salarié a droit à 5 semaines de congés payés par an.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Exemple']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Pour un salarié à temps plein, cela représente 30 jours ouvrables de congés.']]],
        ],
    ],
    $llmResponse2T4
);

$afterT4 = $article2->fresh()->versions()->count();
echo "After TURN 4 (4-tag): {$afterT4} versions\n";

$expected2 = $beforeCount2 + 3; // TURN 1, 2, 4 (NOT TURN 3)
$actual2 = $article2->fresh()->versions()->count();
$status2 = ($actual2 === $expected2) ? '✅ PASS' : '❌ FAIL';

echo "\n===== RESULTS =====\n";
echo "Expected: {$expected2} versions\n";
echo "Actual: {$actual2} versions\n";
echo "Status: {$status2}\n";

$allResults[] = [
    'scenario' => 'Scenario 2: Modification Incrémentale',
    'turns' => 4,
    'expected' => $expected2,
    'actual' => $actual2,
    'status' => $status2,
];

// ==================== FINAL SUMMARY ====================
echo "\n\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║            SCENARIOS 1-2 EXECUTION SUMMARY                 ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n\n";

foreach ($allResults as $result) {
    echo sprintf(
        "%-40s | Turns: %2d | Expected: %2d | Actual: %2d | %s\n",
        $result['scenario'],
        $result['turns'],
        $result['expected'],
        $result['actual'],
        $result['status']
    );
}

$totalPass = count(array_filter($allResults, fn (array $r): bool => $r['status'] === '✅ PASS'));
$totalScenarios = count($allResults);

echo "\n";
echo "Total: {$totalPass}/{$totalScenarios} scenarios passed\n";

if ($totalPass === $totalScenarios) {
    echo "✅ ALL SCENARIOS PASSED!\n";
} else {
    echo "❌ SOME SCENARIOS FAILED!\n";
}
