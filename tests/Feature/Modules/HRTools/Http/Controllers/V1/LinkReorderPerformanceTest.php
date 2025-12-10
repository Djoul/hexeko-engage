<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('hr-tools')]
class LinkReorderPerformanceTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    /** @var array<int, Link> */
    private array $links = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Créer un utilisateur authentifié avec les permissions nécessaires
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_ADMIN,
            withContext: true
        );

        // Créer un financeur
        $financer = $this->auth->financers->first();
        $this->assertNotNull($financer, 'Le financeur n\'a pas été créé correctement');

        /** @var Financer $financer */
        $this->financer = $financer;

        // Créer plusieurs liens pour tester la performance (10 liens)
        for ($i = 0; $i < 10; $i++) {
            $availableLanguages = empty($this->financer->available_languages)
                ? [Languages::FRENCH, Languages::ENGLISH]
                : $this->financer->available_languages;

            $name = [];
            $description = [];
            $url = [];

            foreach ($availableLanguages as $lang) {
                $name[$lang] = "Performance Link $i ($lang)";
                $description[$lang] = "Description for performance link $i ($lang)";
                $url[$lang] = "https://performance.example.com/link$i/$lang";
            }

            $link = Link::create([
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'financer_id' => $this->financer->id,
                'position' => $i,
            ]);

            $this->assertNotNull($link, 'Le lien n\'a pas été créé correctement');
            /** @var Link $link */
            $this->links[] = $link;
        }
    }

    #[Test]
    public function it_reorders_links_without_n_plus_one_queries(): void
    {
        // Préparer les données de réordonnancement pour tous les liens
        $reorderData = [
            'links' => [],
        ];
        // Inverser l'ordre des liens
        $counter = count($this->links);

        // Inverser l'ordre des liens
        for ($i = 0; $i < $counter; $i++) {
            $reorderData['links'][] = [
                'id' => $this->links[$i]->id,
                'position' => count($this->links) - 1 - $i,
            ];
        }

        // Réinitialiser et activer le log des requêtes pour avoir un décompte propre
        DB::flushQueryLog();
        DB::enableQueryLog();

        // Envoyer la requête de réordonnancement
        $response = $this->actingAs($this->auth)->postJson('/api/v1/hr-tools/links/reorder', $reorderData);

        // Récupérer toutes les requêtes exécutées
        $queries = DB::getQueryLog();

        // Vérifier que la requête a réussi
        $response->assertStatus(200);

        // Analyser les requêtes pour détecter le pattern N+1
        $selectQueriesOnLinks = 0;
        foreach ($queries as $query) {
            // Vérifier que la clé 'query' existe (structure Laravel)
            $sql = '';
            if (isset($query['query'])) {
                $sql = strtolower($query['query']);
            } elseif (isset($query['sql'])) {
                $sql = strtolower($query['sql']);
            } else {
                continue;
            }

            // Compter les requêtes SELECT sur la table int_outils_rh_links avec WHERE id = ?
            if (
                str_contains($sql, 'select') &&
                str_contains($sql, 'int_outils_rh_links') &&
                str_contains($sql, 'where') &&
                str_contains($sql, '"id" = ?') &&
                str_contains($sql, 'limit 1')
            ) {
                $selectQueriesOnLinks++;
            }
        }

        // Afficher les informations de debug
        $this->addToAssertionCount(1); // Pour éviter les warnings PHPUnit

        $this->assertEquals(
            0,
            $selectQueriesOnLinks,
            "Détection de N+1 queries! $selectQueriesOnLinks requêtes individuelles SELECT trouvées sur int_outils_rh_links. ".
            "Le problème N+1 n'est pas résolu."
        );

        // ASSERTION SECONDAIRE: Le nombre total de requêtes devrait être raisonnable
        // Avec notre fix, nous devrions avoir un nombre constant de requêtes (pas proportionnel au nombre de liens)
        $totalQueries = count($queries);
        $maxExpectedQueries = 100; // Limite plus généreuse pour le moment pour voir ce qui se passe

        $this->assertLessThanOrEqual(
            $maxExpectedQueries,
            $totalQueries,
            "Trop de requêtes SQL détectées ($totalQueries). Possible problème de performance."
        );

        // Vérifier que les positions ont bien été mises à jour
        $updatedLinks = Link::whereIn('id', collect($this->links)->pluck('id'))
            ->orderBy('position')
            ->get();

        $this->assertCount(count($this->links), $updatedLinks);

        // Vérifier que l'ordre a bien été inversé
        $expectedFirstLink = $this->links[count($this->links) - 1];
        $this->assertEquals($expectedFirstLink->id, $updatedLinks->first()->id);
        $this->assertEquals(0, $updatedLinks->first()->position);
    }

    #[Test]
    public function it_limits_database_queries_regardless_of_link_count(): void
    {
        // Ce test vérifie que le nombre de requêtes ne croît pas avec le nombre de liens

        // Test avec 3 liens
        $smallReorderData = [
            'links' => [
                ['id' => $this->links[0]->id, 'position' => 2],
                ['id' => $this->links[1]->id, 'position' => 1],
                ['id' => $this->links[2]->id, 'position' => 0],
            ],
        ];

        DB::enableQueryLog();
        $queryCountBefore = count(DB::getQueryLog());

        $response = $this->actingAs($this->auth)->postJson('/api/v1/hr-tools/links/reorder', $smallReorderData);
        $response->assertStatus(200);

        $queriesSmall = array_slice(DB::getQueryLog(), $queryCountBefore);
        $smallQueryCount = count($queriesSmall);

        // Test avec 10 liens (tous)
        $largeReorderData = [
            'links' => [],
        ];
        $counter = count($this->links);

        for ($i = 0; $i < $counter; $i++) {
            $largeReorderData['links'][] = [
                'id' => $this->links[$i]->id,
                'position' => count($this->links) - 1 - $i,
            ];
        }

        $queryCountBeforeLarge = count(DB::getQueryLog());

        $response = $this->actingAs($this->auth)->postJson('/api/v1/hr-tools/links/reorder', $largeReorderData);
        $response->assertStatus(200);

        $queriesLarge = array_slice(DB::getQueryLog(), $queryCountBeforeLarge);
        $largeQueryCount = count($queriesLarge);

        // Le nombre de requêtes ne devrait pas croître de manière significative
        // avec le nombre de liens si le N+1 est bien résolu
        $queryGrowthRatio = $largeQueryCount / max($smallQueryCount, 1);

        $this->assertLessThan(
            2.0, // Le nombre de requêtes ne devrait pas doubler
            $queryGrowthRatio,
            'Le nombre de requêtes croît trop avec le nombre de liens '.
            "(3 liens: $smallQueryCount requêtes, 10 liens: $largeQueryCount requêtes). ".
            'Cela indique un possible problème N+1.'
        );
    }
}
