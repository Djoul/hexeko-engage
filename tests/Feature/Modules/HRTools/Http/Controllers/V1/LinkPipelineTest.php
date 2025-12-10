<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Integrations\HRTools\Database\factories\LinkFactory;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['int_outils_rh_links'], scope: 'test')]
#[Group('hr-tools')]
#[Group('link')]
class LinkPipelineTest extends ProtectedRouteTestCase
{
    const HRTools_LINKS_ENDPOINT = '/api/v1/hr-tools/links';

    /**
     * @var User
     */
    protected $auth;

    /**
     * @var Financer
     */
    protected $authFinancer;

    /**
     * @var Financer
     */
    protected $otherFinancer;

    protected function setUp(): void
    {
        parent::setUp();

        // Create authenticated user with division and financer
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::BENEFICIARY,
            financerData: ['available_languages' => [Languages::FRENCH, Languages::ENGLISH]],
            withContext: true,
            returnDetails: true
        );

        // Get financer from parent class property
        $this->authFinancer = $this->currentFinancer;

        resolve(LinkFactory::class)->create([
            'name' => [
                Languages::FRENCH => 'Guide Laravel',
                Languages::ENGLISH => 'Laravel Guide',
            ],
            'description' => [
                Languages::FRENCH => 'Guide complet sur Laravel',
                Languages::ENGLISH => 'Complete guide on Laravel',
            ],
            'url' => [
                Languages::FRENCH => 'https://laravel.com/fr',
                Languages::ENGLISH => 'https://laravel.com',
            ],
            'logo_url' => 'https://laravel.com/logo.png',
            'api_endpoint' => '/api/laravel',
            'front_endpoint' => '/laravel',
            'financer_id' => $this->authFinancer->id,
            'created_at' => '2024-01-01',
            'updated_at' => '2024-02-01',
        ]);

        resolve(LinkFactory::class)->create([
            'name' => [
                Languages::FRENCH => 'Documentation PHP',
                Languages::ENGLISH => 'PHP Documentation',
            ],
            'description' => [
                Languages::FRENCH => 'Documentation officielle PHP',
                Languages::ENGLISH => 'Official PHP documentation',
            ],
            'url' => [
                Languages::FRENCH => 'https://php.net/fr',
                Languages::ENGLISH => 'https://php.net',
            ],
            'logo_url' => 'https://php.net/logo.png',
            'api_endpoint' => '/api/php',
            'front_endpoint' => '/php',
            'financer_id' => $this->authFinancer->id,
            'created_at' => '2023-01-01',
            'updated_at' => '2023-02-01',
        ]);

        // Créer un autre financer pour tester le filtrage par financer_id
        $this->otherFinancer = ModelFactory::createFinancer([
            'division_id' => $this->currentDivision->id,
            'available_languages' => [Languages::FRENCH, Languages::ENGLISH],
        ]);

        // Attach the user to the other financer so they can see its links
        $this->auth->financers()->attach($this->otherFinancer->id, ['active' => true, 'role' => RoleDefaults::FINANCER_ADMIN]);

        // Reload financers and hydrate authorization context after attaching second financer
        $this->auth->load('financers', 'roles');
        $this->hydrateAuthorizationContext($this->auth);

        resolve(LinkFactory::class)->create([
            'name' => [
                Languages::FRENCH => 'Documentation PHP',
                Languages::ENGLISH => 'PHP Documentation',
            ],
            'description' => [
                Languages::FRENCH => 'Documentation officielle PHP',
                Languages::ENGLISH => 'Official PHP documentation',
            ],
            'url' => [
                Languages::FRENCH => 'https://php.net/fr',
                Languages::ENGLISH => 'https://php.net',
            ],
            'logo_url' => 'https://php.net/logo.png',
            'api_endpoint' => '/api/php',
            'front_endpoint' => '/php',
            'financer_id' => $this->otherFinancer->id,
            'created_at' => '2023-01-01',
            'updated_at' => '2023-03-01',
        ]);

    }

    #[Test]
    public function it_filters_links_by_name(): void
    {
        $this->assertDatabaseCount('int_outils_rh_links', 3);
        $response = $this->actingAs($this->auth)->get(self::HRTools_LINKS_ENDPOINT.'?name=Laravel');

        $response->assertOk()
            ->assertJsonCount(1, 'data');

        $responseData = $response->json();

        $this->assertEquals('Laravel Guide', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_filters_links_by_url(): void
    {
        // Verify links are created
        $this->assertDatabaseCount('int_outils_rh_links', 3);
        $phpLinks = Link::where('url->fr-FR', 'like', '%php.net%')->get();
        $this->assertCount(2, $phpLinks, 'Should have 2 PHP links in database');

        $response = $this->actingAs($this->auth)->get(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&url=php.net");

        $response->assertOk();
        $responseData = $response->json();

        // Should only return PHP link from current financer (authFinancer), not from otherFinancer
        $response->assertJsonCount(1, 'data');
        $this->assertEquals('PHP Documentation', $responseData['data'][0]['name']);
    }

    #[Test]
    public function it_filters_links_by_financer_id(): void
    {
        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT.'?financer_id='.$this->otherFinancer->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.financer_id', $this->otherFinancer->id);
    }

    #[Test]
    public function it_filters_links_by_created_at_date(): void
    {

        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT.'?date_from=2024-01-01&date_from_fields[]=created_at');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.created_at', '2024-01-01T00:00:00.000000Z');
    }

    #[Test]
    public function it_filters_links_by_date_from(): void
    {
        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_from=2024-01-01");
        $response->assertOk()->assertJsonCount(1, 'data');
        $responseData = $response->json();
        $this->assertEquals('Laravel Guide', $responseData['data'][0]['name']);

        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_from=2024-02-01&date_from_fields=updated_at");
        $response->assertOk()->assertJsonCount(1, 'data');
        $responseData = $response->json();
        $this->assertEquals('Laravel Guide', $responseData['data'][0]['name']);

        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_from=2023-03-01&date_from_fields[]=created_at&date_from_fields[]=updated_at");

        // Should return 1 link: Link 1 (created_at 2024-01-01) from authFinancer
        // Link 3 (updated_at 2023-03-01) is from otherFinancer and should not be visible
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_links_by_date_to(): void
    {
        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_to=2023-01-31");
        // Should only return 1 link: Link 2 (created_at 2023-01-01) from authFinancer
        // Link 3 is from otherFinancer and should not be visible
        $response->assertOk()->assertJsonCount(1, 'data');

        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_to=2023-02-28&date_to_fields=updated_at");
        // Should only return 1 link: Link 2 with updated_at = 2023-02-01
        $response->assertOk()->assertJsonCount(1, 'data');

        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT."?financer_id={$this->authFinancer->id}&date_to=2023-02-01&date_to_fields[]=created_at&date_to_fields[]=updated_at");
        // Should return 1 link: Link 2 from authFinancer
        // Link 3 is from otherFinancer and should not be visible
        $response->assertOk()->assertJsonCount(1, 'data');

    }

    #[Test]
    public function it_returns_no_links_if_filters_do_not_match(): void
    {
        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT.'?name=Symfony');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_validates_query_params(): void
    {
        $response = $this->actingAs($this->auth)->getJson(self::HRTools_LINKS_ENDPOINT.'?created_at=invalid-date');

        $response->assertStatus(422);
    }

    #[Test]
    public function it_applies_filters_on_show_method(): void
    {
        $link = Link::where('financer_id', $this->authFinancer->id)->first();
        $this->assertNotNull($link, 'Aucun lien trouvé pour le test');

        $endpoint = self::HRTools_LINKS_ENDPOINT."/{$link->id}";
        $response = $this->actingAs($this->auth)->getJson($endpoint);
        $response->assertOk()->assertJsonPath('data.id', $link->id);

        $response = $this->actingAs($this->auth)
            ->getJson(self::HRTools_LINKS_ENDPOINT."/{$link->id}?name=PHP");
        $response->assertOk()->assertJsonPath('data.id', $link->id);
    }
}
