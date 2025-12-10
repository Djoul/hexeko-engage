<?php

namespace Tests\Feature\Modules\HRTools\Http\Controllers\V1;

use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('hr-tools')]
class MeEndpointPinnedLinksTest extends ProtectedRouteTestCase
{
    private User $user;

    private Link $link1;

    private Link $link2;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an authenticated user with necessary permissions
        $this->user = $this->createAuthUser();
        $this->actingAs($this->user);

        // Get the financer from the authenticated user
        $financer = $this->user->financers->first();
        $this->assertNotNull($financer, 'Financer was not created correctly');
        $this->financer = $financer;

        // Create links
        $this->link1 = Link::create([
            'name' => 'Test Link 1',
            'description' => 'Description for test link 1',
            'url' => 'https://example.com/testlink1',
            'financer_id' => $this->financer->id,
            'position' => 1,
        ]);

        $this->link2 = Link::create([
            'name' => 'Test Link 2',
            'description' => 'Description for test link 2',
            'url' => 'https://example.com/testlink2',
            'financer_id' => $this->financer->id,
            'position' => 2,
        ]);

        // Pin the links
        $this->user->pinnedHRToolsLinks()->attach($this->link1->id, ['pinned' => true]);
        $this->user->pinnedHRToolsLinks()->attach($this->link2->id, ['pinned' => false]); // Not pinned
    }

    #[Test]
    public function it_includes_pinned_links_in_me_endpoint_response(): void
    {
        // Make an actual HTTP call to the /me endpoint
        $response = $this->getJson('/api/v1/me');

        // Check that the request was successful
        $response->assertStatus(200);

        // Check that pinned links are included in the response
        $response->assertJsonStructure(['data' => ['pinned_HRTools_links']]);

        // Get the response data
        $responseData = $response->json();

        // Check that only pinned links are included
        $this->assertCount(1, $responseData['data']['pinned_HRTools_links']);

        // Check that the pinned link is the correct one
        $this->assertEquals($this->link1->id, $responseData['data']['pinned_HRTools_links'][0]['id']);
    }

    #[Test]
    public function it_does_not_include_unpinned_links_in_me_endpoint_response(): void
    {
        // Make an actual HTTP call to the /me endpoint
        $response = $this->getJson('/api/v1/me');

        // Check that the request was successful
        $response->assertStatus(200);

        // Check that pinned links are included in the response
        $response->assertJsonStructure(['data' => ['pinned_HRTools_links']]);

        // Get the response data
        $responseData = $response->json();

        // Check that unpinned links are not included
        $pinnedLinkIds = collect($responseData['data']['pinned_HRTools_links'])->pluck('id')->toArray();
        $this->assertContains($this->link1->id, $pinnedLinkIds);
        $this->assertNotContains($this->link2->id, $pinnedLinkIds);
    }

    #[Test]
    public function it_does_not_include_pinned_links_in_non_me_endpoint_response(): void
    {
        // Create another user to test the non-me endpoint
        $anotherUser = User::factory()->create();

        // Attach anotherUser to the same financer as authUser for multi-tenant isolation
        $anotherUser->financers()->attach($this->financer->id, ['active' => true]);

        // Make an actual HTTP call to get the user (non-me endpoint)
        $response = $this->getJson("/api/v1/users/{$anotherUser->id}");

        // Check that the request was successful
        $response->assertStatus(200);

        // Check that pinned links are not included in the response
        $response->assertJsonMissing(['pinned_HRTools_links']);
    }
}
