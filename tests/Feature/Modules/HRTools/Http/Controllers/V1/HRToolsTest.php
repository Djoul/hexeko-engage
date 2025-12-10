<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Integrations\HRTools\Database\factories\LinkFactory;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['int_outils_rh_links'], scope: 'test')]
#[Group('hr-tools')]
class HRToolsTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/hr-tools/links';

    private Financer $financer;

    #[Test]
    public function get_all_links_returns_collection_of_links(): void
    {
        // Create links with translatable content
        resolve(LinkFactory::class)->create([
            'name' => [
                Languages::FRENCH => 'Lien 1',
                Languages::ENGLISH => 'Link 1',
            ],
            'financer_id' => $this->financer->id,
        ]);

        resolve(LinkFactory::class)->create([
            'name' => [
                Languages::FRENCH => 'Lien 2',
                Languages::ENGLISH => 'Link 2',
            ],
            'financer_id' => $this->financer->id,
        ]);

        $response = $this->actingAs($this->auth)->get(self::URI);

        $response->assertStatus(200);

        $this->assertDatabaseCount('int_outils_rh_links', 2);

        $response->assertJsonStructure(
            [
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'url',
                        'logo_url',
                        'position',
                        'financer_id',
                        'available_languages',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]
        );

    }

    #[Test]
    public function store_validates_required_fields(): void
    {
        // Set available languages for the financer
        $this->financer->available_languages = [Languages::FRENCH, Languages::ENGLISH];
        $this->financer->save();

        $data = [
            'name' => [],
            'url' => [],
            'financer_id' => $this->financer->id,
        ];

        try {
            $this->actingAs($this->auth)->postJson(self::URI, $data)->assertStatus(422);
        } catch (ValidationException $e) {
            $this->assertNotNull($e->getResponse());
        }
    }

    #[Test]
    public function store_creates_new_link_when_validation_passes(): void
    {
        $this->assertDatabaseCount('int_outils_rh_links', 0);

        // Set available languages for the financer
        $this->financer->available_languages = [Languages::FRENCH, Languages::ENGLISH];
        $this->financer->save();

        $data = [
            'name' => [
                Languages::FRENCH => 'Lien de test',
                Languages::ENGLISH => 'Test Link',
            ],
            'description' => [
                Languages::FRENCH => 'Description en français',
                Languages::ENGLISH => 'English description',
            ],
            'url' => [
                Languages::FRENCH => 'https://example.fr',
                Languages::ENGLISH => 'https://example.com',
            ],
            'api_endpoint' => '/api/test-link',
            'front_endpoint' => '/test-link',
            'financer_id' => $this->financer->id,
        ];

        $response = $this->actingAs($this->auth)->post(self::URI, $data);

        $response->assertStatus(201);

        $this->assertDatabaseCount('int_outils_rh_links', 1);

        // Get the created link from the database
        $link = Link::first();

        // Assert that the translatable fields have the correct values
        $this->assertEquals('Lien de test', $link->getTranslation('name', Languages::FRENCH));
        $this->assertEquals('Test Link', $link->getTranslation('name', Languages::ENGLISH));
        $this->assertEquals('Description en français', $link->getTranslation('description', Languages::FRENCH));
        $this->assertEquals('English description', $link->getTranslation('description', Languages::ENGLISH));
        $this->assertEquals('https://example.fr', $link->getTranslation('url', Languages::FRENCH));
        $this->assertEquals('https://example.com', $link->getTranslation('url', Languages::ENGLISH));

        // Assert that the non-translatable fields have the correct values
        $this->assertEquals($data['api_endpoint'], $link->api_endpoint);
        $this->assertEquals($data['front_endpoint'], $link->front_endpoint);
    }

    #[Test]
    public function it_can_update_link(): void
    {
        /** @var Link $link */
        $link = resolve(LinkFactory::class)
            ->create(
                [
                    'name' => [
                        Languages::FRENCH => 'Lien de test',
                        Languages::ENGLISH => 'Test Link',
                    ],
                    'financer_id' => $this->financer->id,
                ]);

        $this->assertInstanceOf(
            Link::class,
            $link);

        $updatedData = [
            'name' => [
                Languages::FRENCH => 'Nom mis à jour',
                Languages::ENGLISH => 'Updated Name',
            ],
            'description' => [
                Languages::FRENCH => 'Description mise à jour',
                Languages::ENGLISH => 'Updated description',
            ],
            'url' => [
                Languages::FRENCH => 'https://example-updated.fr',
                Languages::ENGLISH => 'https://example-updated.com',
            ],
            'logo_url' => $this->faker->url,
            'api_endpoint' => '/api/updated-link',
            'front_endpoint' => '/updated-link',
            'financer_id' => $link->financer_id,
        ];

        if ($link->created_at) {
            $updatedData['created_at'] = $link->created_at->format('Y-m-d H:i:s');
        }

        if ($link->updated_at) {
            $updatedData['updated_at'] = $link->updated_at->format('Y-m-d H:i:s');
        }

        $this->assertDatabaseCount('int_outils_rh_links', 1);

        $response = $this->actingAs($this->auth)->put(self::URI."/{$link->id}", $updatedData);

        $response->assertStatus(200);

        $this->assertDatabaseCount('int_outils_rh_links', 1);

        // Refresh the link from the database
        $link->refresh();

        // Assert that the translatable fields have the correct values
        $this->assertEquals('Nom mis à jour', $link->getTranslation('name', Languages::FRENCH));
        $this->assertEquals('Updated Name', $link->getTranslation('name', Languages::ENGLISH));
        $this->assertEquals('Description mise à jour', $link->getTranslation('description', Languages::FRENCH));
        $this->assertEquals('Updated description', $link->getTranslation('description', Languages::ENGLISH));
        $this->assertEquals('https://example-updated.fr', $link->getTranslation('url', Languages::FRENCH));
        $this->assertEquals('https://example-updated.com', $link->getTranslation('url', Languages::ENGLISH));

        // Assert that the non-translatable fields have the correct values
        $this->assertEquals($updatedData['api_endpoint'], $link->api_endpoint);
        $this->assertEquals($updatedData['front_endpoint'], $link->front_endpoint);
    }

    /* Rest of the tests remain unchanged */

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with financer and context
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_ADMIN,
            financerData: [
                'name' => 'Test Financer',
                'available_languages' => [Languages::FRENCH, Languages::ENGLISH],
            ],
            withContext: true,
            returnDetails: true
        );

        $this->financer = $this->currentFinancer;
    }
}
