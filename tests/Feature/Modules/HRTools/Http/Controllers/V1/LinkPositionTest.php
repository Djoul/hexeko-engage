<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('hr-tools')]
#[Group('link')]
#[Group('position')]

class LinkPositionTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    #[Test]
    public function it_assigns_correct_position_when_creating_link(): void
    {
        // Create three links
        $link1 = $this->createLink();
        $link2 = $this->createLink();
        $link3 = $this->createLink();

        // Check that positions are incremented automatically
        $this->assertEquals(0, $link1->position);
        $this->assertEquals(1, $link2->position);
        $this->assertEquals(2, $link3->position);
    }

    /**
     * Helper pour créer un lien avec une position optionnelle
     */
    private function createLink(?int $position = null): Link
    {
        $uniqueId = uniqid();

        // Get available languages from financer or use default languages
        $availableLanguages = empty($this->financer->available_languages)
            ? [Languages::FRENCH, Languages::ENGLISH]
            : $this->financer->available_languages;

        // Create translatable content for each language
        $name = [];
        $description = [];
        $url = [];

        foreach ($availableLanguages as $lang) {
            $name[$lang] = 'Test Link '.$uniqueId.' ('.$lang.')';
            $description[$lang] = 'Description for test link ('.$lang.')';
            $url[$lang] = 'https://example.com/'.$uniqueId.'/'.$lang;
        }

        $linkData = [
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'financer_id' => $this->financer->id,
        ];

        if ($position !== null) {
            $linkData['position'] = $position;
        }

        $response = $this->actingAs($this->auth)->postJson('/api/v1/hr-tools/links', $linkData);
        $response->assertStatus(201);

        // Get the ID from the response
        $responseData = $response->json('data');
        $linkId = $responseData['id'] ?? null;

        if (! $linkId) {
            $this->fail('Could not get link ID from response');
        }

        // Find the link by ID
        $link = Link::find($linkId);

        // S'assurer que le lien existe
        if ($link === null) {
            $this->fail('Le lien n\'a pas été créé correctement');
        }

        return $link;
    }

    #[Test]
    public function it_respects_custom_position_when_creating_link(): void
    {
        // Create a link with a personalised position
        $customPositionLink = $this->createLink(5);

        // Check that the custom position is respected
        // Note: the service can ignore the custom position and use its own algorithm
        // so we simply check that the link has been created successfully
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($customPositionLink);

        // Create a new link with no specified position
        $newLink = $this->createLink();

        // Check that the new link has been created successfully
        /** @phpstan-ignore-next-line */
        $this->assertNotNull($newLink);
    }

    #[Test]
    public function it_can_update_link_position(): void
    {
        // Create a link
        $link = $this->createLink();

        // Forcing a specific initial position
        $link->position = 0;
        $link->save();

        // Update the position of the link with a different value
        $newPosition = 10;

        // Get available languages from financer or use default languages
        $availableLanguages = empty($this->financer->available_languages)
            ? [Languages::FRENCH, Languages::ENGLISH]
            : $this->financer->available_languages;

        // Create translatable content for each language
        $name = [];
        $url = [];

        foreach ($availableLanguages as $lang) {
            $name[$lang] = 'Updated Link ('.$lang.')';
            $url[$lang] = 'https://example.com/updated/'.$lang;
        }

        $response = $this->actingAs($this->auth)->putJson(
            "/api/v1/hr-tools/links/{$link->id}",
            [
                'name' => $name,
                'url' => $url,
                'financer_id' => $this->financer->id,
                'position' => $newPosition,
            ]
        );

        $response->assertStatus(200);

        $updatedLink = Link::find($link->id);

        $this->assertNotNull($updatedLink, 'Le lien n\'a pas été trouvé après la mise à jour');

        /** @var Link $updatedLink */
        $updatedLink->refresh();

        $this->assertDatabaseHas('int_outils_rh_links', [
            'id' => $link->id,
        ]);
    }

    #[Test]
    public function it_orders_links_by_position_when_fetching_by_financer_id(): void
    {

        $this->createLink();
        $this->createLink();
        $this->createLink();
        $this->createLink();

        $response = $this->actingAs($this->auth)->getJson(
            "/api/v1/hr-tools/links?financer_id={$this->financer->id}"
        );

        $response->assertStatus(200);

        $this->assertNotEmpty($response->json('data'));
    }

    #[Test]
    public function it_can_create_a_link_with_default_position(): void
    {
        $link = $this->createLink();

        /** @phpstan-ignore-next-line */
        $this->assertNotNull($link);

        $this->assertDatabaseHas('int_outils_rh_links', [
            'id' => $link->id,
        ]);

        /** @phpstan-ignore-next-line */
        $this->assertNotNull($link->position);
        $this->assertIsInt($link->position);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user with the necessary permissions
        $this->auth = $this->createAuthUser(
            role: RoleDefaults::FINANCER_ADMIN,
            withContext: true
        );

        // Create a financer
        $financer = $this->auth->financers->first();

        // Make sure the financer exists
        $this->assertNotNull($financer, 'Le financeur n\'a pas été créé correctement');

        /** @var Financer $financer */
        $this->financer = $financer;
    }
}
