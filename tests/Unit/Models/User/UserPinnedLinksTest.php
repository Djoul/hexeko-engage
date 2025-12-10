<?php

namespace Tests\Unit\Models\User;

use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class UserPinnedLinksTest extends TestCase
{
    private User $user;

    private Link $link;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a financer
        $this->financer = Financer::factory()->create();

        // Create a user
        $this->user = User::factory()->create();

        // Create a link
        $this->link = Link::create([
            'name' => 'Test Link',
            'description' => 'Description for test link',
            'url' => 'https://example.com/testlink',
            'financer_id' => $this->financer->id,
            'position' => 1,
        ]);
    }

    #[Test]
    public function user_can_pin_a_link(): void
    {
        // Pin the link
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Check that the link is pinned
        $pinnedLinks = $this->user->pinnedHRToolsLinks()->wherePivot('pinned', true)->get();

        $this->assertCount(1, $pinnedLinks);
        $this->assertEquals($this->link->id, $pinnedLinks->first()->id);
    }

    #[Test]
    public function user_can_unpin_a_link(): void
    {
        // First pin the link
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Then unpin it
        $this->user->pinnedHRToolsLinks()->updateExistingPivot($this->link->id, ['pinned' => false]);

        // Check that the link is unpinned
        $pinnedLinks = $this->user->pinnedHRToolsLinks()->wherePivot('pinned', true)->get();
        $unpinnedLinks = $this->user->pinnedHRToolsLinks()->wherePivot('pinned', false)->get();

        $this->assertCount(0, $pinnedLinks);
        $this->assertCount(1, $unpinnedLinks);
        $this->assertEquals($this->link->id, $unpinnedLinks->first()->id);
    }

    #[Test]
    public function user_can_get_only_pinned_links(): void
    {
        // Create multiple links
        $link2 = Link::create([
            'name' => 'Test Link 2',
            'description' => 'Description for test link 2',
            'url' => 'https://example.com/testlink2',
            'financer_id' => $this->financer->id,
            'position' => 2,
        ]);

        $link3 = Link::create([
            'name' => 'Test Link 3',
            'description' => 'Description for test link 3',
            'url' => 'https://example.com/testlink3',
            'financer_id' => $this->financer->id,
            'position' => 3,
        ]);

        // Pin some links and unpin others
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user->pinnedHRToolsLinks()->attach($link2->id, ['pinned' => false]);
        $this->user->pinnedHRToolsLinks()->attach($link3->id, ['pinned' => true]);

        // Get only pinned links
        $pinnedLinks = $this->user->pinnedHRToolsLinks()->wherePivot('pinned', true)->get();

        // Check that only the pinned links are returned
        $this->assertCount(2, $pinnedLinks);
        $this->assertTrue($pinnedLinks->contains('id', $this->link->id));
        $this->assertTrue($pinnedLinks->contains('id', $link3->id));
        $this->assertFalse($pinnedLinks->contains('id', $link2->id));
    }

    #[Test]
    public function user_can_toggle_pin_status(): void
    {
        // Pin the link
        $this->user->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Check that the link is pinned
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', true)
                ->exists()
        );

        // Toggle to unpinned
        $this->user->pinnedHRToolsLinks()->updateExistingPivot($this->link->id, ['pinned' => false]);

        // Check that the link is unpinned
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', false)
                ->exists()
        );

        // Toggle back to pinned
        $this->user->pinnedHRToolsLinks()->updateExistingPivot($this->link->id, ['pinned' => true]);

        // Check that the link is pinned again
        $this->assertTrue(
            $this->user->pinnedHRToolsLinks()
                ->where('link_id', $this->link->id)
                ->wherePivot('pinned', true)
                ->exists()
        );
    }
}
