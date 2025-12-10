<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\Gender;
use App\Enums\IDP\RoleDefaults;
use App\Models\Financer;
use Illuminate\Support\Facades\App;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('gender')]
#[Group('user')]
class GenderControllerTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Gender controller is not yet implemented.');
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $this->financer = $this->auth->financers->first();

        authorizationContext()->hydrateFromRequest($this->auth);
    }

    #[Test]
    public function it_can_list_genders(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'label',
                        'value',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_returns_all_gender_values(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $values = array_column($data, 'value');

        // Verify all enum values are present
        $this->assertContains(Gender::MALE, $values);
        $this->assertContains(Gender::FEMALE, $values);
        $this->assertContains(Gender::UNISEX, $values);
        $this->assertContains(Gender::OTHER, $values);
        $this->assertContains(Gender::NOT_SPECIFIED, $values);
        $this->assertCount(5, $values);
    }

    #[Test]
    public function it_returns_genders_with_english_labels(): void
    {
        App::setLocale('en-GB');

        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $labels = array_column($data, 'label');

        $this->assertContains('Male', $labels);
        $this->assertContains('Female', $labels);
        $this->assertContains('Unisex', $labels);
        $this->assertContains('Other', $labels);
        $this->assertContains('Not specified', $labels);
    }

    #[Test]
    public function it_returns_genders_with_french_labels(): void
    {
        App::setLocale('fr-FR');

        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $labels = array_column($data, 'label');

        $this->assertContains('Homme', $labels);
        $this->assertContains('Femme', $labels);
        $this->assertContains('Unisexe', $labels);
        $this->assertContains('Autre', $labels);
        $this->assertContains('Non spécifié', $labels);
    }

    #[Test]
    public function it_returns_genders_with_dutch_labels(): void
    {
        App::setLocale('nl-NL');

        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $data = $response->json('data');
        $labels = array_column($data, 'label');

        $this->assertContains('Man', $labels);
        $this->assertContains('Vrouw', $labels);
        $this->assertContains('Unisex', $labels);
        $this->assertContains('Anders', $labels);
        $this->assertContains('Niet gespecificeerd', $labels);
    }

    #[Test]
    public function it_returns_consistent_structure_for_each_gender(): void
    {
        $response = $this->actingAs($this->auth)
            ->getJson(route('genders.index', ['financer_id' => $this->financer->id]));

        $response->assertStatus(200);

        $data = $response->json('data');

        foreach ($data as $gender) {
            $this->assertArrayHasKey('label', $gender);
            $this->assertArrayHasKey('value', $gender);
            $this->assertIsString($gender['label']);
            $this->assertIsString($gender['value']);
            $this->assertNotEmpty($gender['label']);
            $this->assertNotEmpty($gender['value']);
        }
    }
}
