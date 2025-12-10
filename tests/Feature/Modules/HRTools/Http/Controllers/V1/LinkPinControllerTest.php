<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Enums\Languages;
use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('hr-tools')]
#[Group('link')]
#[Group('pin')]

class LinkPinControllerTest extends ProtectedRouteTestCase
{
    private Financer $financer;

    private Link $link;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an authenticated user with necessary permissions
        $this->user = $this->createAuthUser(withContext: true);
        $this->actingAs($this->user);

        // Get the financer from the authenticated user
        $financer = $this->user->financers->first();
        $this->assertNotNull($financer, 'Financer was not created correctly');

        // Set available languages for the financer
        $financer->available_languages = [Languages::FRENCH, Languages::ENGLISH];
        $financer->save();

        $this->financer = $financer;

        // Create a test link with translatable content
        $this->link = Link::create([
            'name' => [
                Languages::FRENCH => 'Lien de test',
                Languages::ENGLISH => 'Test Link',
            ],
            'description' => [
                Languages::FRENCH => 'Description en français',
                Languages::ENGLISH => 'Description for test link',
            ],
            'url' => [
                Languages::FRENCH => 'https://example.fr/testlink',
                Languages::ENGLISH => 'https://example.com/testlink',
            ],
            'financer_id' => $this->financer->id,
            'position' => 1,
        ]);

        $this->assertNotNull($this->link, 'Link was not created correctly');
    }

    #[Test]
    public function it_can_pin_a_link(): void
    {
        // Send request to pin the link
        $response = $this->postJson("/api/v1/hr-tools/links/{$this->link->id}/toggle-pin");

        // Check that the request was successful
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Link pinned successfully',
            'pinned' => true,
        ]);

        // Verify that the link is now pinned in the database
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', true)
                ->exists(),
            'Link was not pinned in the database'
        );
    }

    #[Test]
    public function it_can_unpin_a_link(): void
    {
        // First pin the link
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Verify the link is pinned
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', true)
                ->exists(),
            'Link was not pinned in the database before the test'
        );

        // Send request to unpin the link
        $response = $this->postJson("/api/v1/hr-tools/links/{$this->link->id}/toggle-pin");

        // Check that the request was successful
        //        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Link unpinned successfully',
            'pinned' => false,
        ]);

        // Verify that the link is now unpinned in the database
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', false)
                ->exists(),
            'Link was not unpinned in the database'
        );
    }

    #[Test]
    public function it_can_repin_an_unpinned_link(): void
    {
        // First pin the link, then unpin it
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user->pinnedHRToolsLinks()->updateExistingPivot($this->link->id, ['pinned' => false]);

        // Verify the link is unpinned
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', false)
                ->exists(),
            'Link was not unpinned in the database before the test'
        );

        // Send request to repin the link
        $response = $this->postJson("/api/v1/hr-tools/links/{$this->link->id}/toggle-pin");

        // Check that the request was successful
        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Link pinned successfully',
            'pinned' => true,
        ]);

        // Verify that the link is now pinned again in the database
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', true)
                ->exists(),
            'Link was not repinned in the database'
        );
    }

    #[Test]
    public function it_returns_404_for_nonexistent_link(): void
    {
        $this->withoutExceptionHandling();
        // Generate a UUID that doesn't exist
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Send request with non-existent link ID
        $response = $this->postJson("/api/v1/hr-tools/links/{$nonExistentId}/toggle-pin");

        // Check that the request returns a 404 status
        $response->assertStatus(404);
        $response->assertJson([
            'message' => 'Link not found',
        ]);
    }
}
