<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('hr-tools')]
#[Group('link')]
#[Group('reorder')]

class LinkReorderTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    /** @var array<int, Link> */
    private array $links = [];

    /**
     * @var array<int, array<string, int>>
     */
    private array $linkData = [];

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

        // S'assurer que le financeur existe
        $this->assertNotNull($financer, 'Le financeur n\'a pas été créé correctement');

        /** @var Financer $financer */
        $this->financer = $financer;

        // Créer quelques liens pour les tests
        for ($i = 0; $i < 3; $i++) {
            // Get available languages from financer or use default languages
            $availableLanguages = empty($this->financer->available_languages)
                ? [Languages::FRENCH, Languages::ENGLISH]
                : $this->financer->available_languages;

            // Create translatable content for each language
            $name = [];
            $description = [];
            $url = [];

            foreach ($availableLanguages as $lang) {
                $name[$lang] = "Link $i ($lang)";
                $description[$lang] = "Description for link $i ($lang)";
                $url[$lang] = "https://example.com/link$i/$lang";
            }

            $link = Link::create([
                'name' => $name,
                'description' => $description,
                'url' => $url,
                'financer_id' => $this->financer->id,
                'position' => $i,
            ]);

            // S'assurer que le lien a été créé
            $this->assertNotNull($link, 'Le lien n\'a pas été créé correctement');

            /** @var Link $link */
            $this->links[] = $link;

            // Stocker les données du lien pour les tests
            $this->linkData[] = [
                'id' => $link->id,
                'position' => $i,
            ];
        }
    }

    #[Test]
    public function it_can_reorder_links(): void
    {
        // Préparer les données pour la requête de réordonnancement
        $reorderData = [
            'links' => [
                [
                    'id' => $this->linkData[2]['id'],
                    'position' => 0,
                ],
                [
                    'id' => $this->linkData[0]['id'],
                    'position' => 1,
                ],
                [
                    'id' => $this->linkData[1]['id'],
                    'position' => 2,
                ],
            ],
        ];

        // Envoyer la requête de réordonnancement
        $response = $this->actingAs($this->auth)->postJson('/api/v1/hr-tools/links/reorder', $reorderData);

        // Vérifier que la requête a réussi
        $response->assertStatus(200);

        // Vérifier que les positions ont été mises à jour en base de données
        $link2 = Link::find($this->linkData[2]['id']);
        $link0 = Link::find($this->linkData[0]['id']);
        $link1 = Link::find($this->linkData[1]['id']);

        // S'assurer que les liens existent
        $this->assertNotNull($link2, 'Le lien 2 n\'a pas été trouvé après la mise à jour');
        $this->assertNotNull($link0, 'Le lien 0 n\'a pas été trouvé après la mise à jour');
        $this->assertNotNull($link1, 'Le lien 1 n\'a pas été trouvé après la mise à jour');

        /**
         * @var Link $link2
         * @var Link $link0
         * @var Link $link1
         */
        $this->assertEquals(0, $link2->position);
        $this->assertEquals(1, $link0->position);
        $this->assertEquals(2, $link1->position);

        // Récupérer les liens depuis la base de données pour vérifier l'ordre
        /** @var Collection<int, Link> $updatedLinks */
        $updatedLinks = Link::whereIn('id', [
            $this->linkData[0]['id'],
            $this->linkData[1]['id'],
            $this->linkData[2]['id'],
        ])
            ->orderBy('position')
            ->get();

        // Vérifier l'ordre des liens
        $this->assertCount(3, $updatedLinks);

        // Vérifier que les liens sont dans le bon ordre
        $this->assertEquals($this->linkData[2]['id'], $updatedLinks[0]->id);
        $this->assertEquals($this->linkData[0]['id'], $updatedLinks[1]->id);
        $this->assertEquals(
            // phpstan-ignore-next-line
            $this->linkData[1]['id'],
            // phpstan-ignore-next-line
            $updatedLinks[2]->id
        );
    }

    #[Test]
    public function it_validates_link_ids(): void
    {
        // Nous allons utiliser une approche différente pour ce test
        // Créer un ID qui n'existe pas mais qui est au format UUID valide
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        $invalidData = [
            ['id' => $nonExistentId, 'position' => 0],
            ['id' => $this->links[0]->id, 'position' => 1],
        ];

        // Envoyer la requête avec des données invalides
        $response = $this->actingAs($this->auth)->postJson(
            '/api/v1/hr-tools/links/reorder',
            ['links' => $invalidData]
        );

        // Vérifier que la requête échoue (422 ou 404)
        $this->assertTrue(
            in_array($response->status(), [422, 404, 400], true)
        );
    }

    #[Test]
    public function it_validates_position_values(): void
    {
        // Préparer des données avec une position négative
        $invalidData = [
            ['id' => $this->links[0]->id, 'position' => -1],
            ['id' => $this->links[1]->id, 'position' => 1],
        ];

        // Envoyer la requête avec des données invalides
        $response = $this->actingAs($this->auth)->postJson(
            '/api/v1/hr-tools/links/reorder',
            ['links' => $invalidData]
        );

        // Vérifier que la validation échoue
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['links.0.position']);
    }
}
