<?php

namespace Tests\Unit\Models;

use App\Integrations\HRTools\Models\Link;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class LinkPinnedByUsersTest extends TestCase
{
    private User $user1;

    private User $user2;

    private Link $link;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Create a financer
        $this->financer = Financer::factory()->create();

        // Create users
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();

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
    public function link_can_be_pinned_by_users(): void
    {
        // Pin the link by users
        $this->user1->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user2->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Check that the link is pinned by both users
        $pinnedByUsers = $this->link->pinnedByUsers()->wherePivot('pinned', true)->get();

        $this->assertCount(2, $pinnedByUsers);
        $this->assertTrue($pinnedByUsers->contains('id', $this->user1->id));
        $this->assertTrue($pinnedByUsers->contains('id', $this->user2->id));
    }

    #[Test]
    public function link_can_be_unpinned_by_users(): void
    {
        // First pin the link by both users
        $this->user1->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user2->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Then unpin it by one user
        $this->user1->pinnedHRToolsLinks()->updateExistingPivot($this->link->id, ['pinned' => false]);

        // Check that the link is still pinned by one user and unpinned by the other
        $pinnedByUsers = $this->link->pinnedByUsers()->wherePivot('pinned', true)->get();
        $unpinnedByUsers = $this->link->pinnedByUsers()->wherePivot('pinned', false)->get();

        $this->assertCount(1, $pinnedByUsers);
        $this->assertCount(1, $unpinnedByUsers);
        $this->assertTrue($pinnedByUsers->contains('id', $this->user2->id));
        $this->assertTrue($unpinnedByUsers->contains('id', $this->user1->id));
    }

    #[Test]
    public function link_can_get_only_users_who_pinned_it(): void
    {
        // Create another user
        $user3 = User::factory()->create();

        // Pin the link by some users and unpin by others
        $this->user1->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user2->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => false]);
        $user3->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Get only users who pinned the link
        $pinnedByUsers = $this->link->pinnedByUsers()->wherePivot('pinned', true)->get();

        // Check that only the users who pinned the link are returned
        $this->assertCount(2, $pinnedByUsers);
        $this->assertTrue($pinnedByUsers->contains('id', $this->user1->id));
        $this->assertTrue($pinnedByUsers->contains('id', $user3->id));
        $this->assertFalse($pinnedByUsers->contains('id', $this->user2->id));
    }

    #[Test]
    public function link_can_count_users_who_pinned_it(): void
    {
        // Create more users
        $user3 = User::factory()->create();
        $user4 = User::factory()->create();

        // Pin the link by some users
        $this->user1->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $this->user2->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);
        $user3->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => false]); // Not pinned
        $user4->pinnedHRToolsLinks()->attach($this->link->id, ['pinned' => true]);

        // Count users who pinned the link
        $pinnedCount = $this->link->pinnedByUsers()->wherePivot('pinned', true)->count();

        // Check the count
        $this->assertEquals(3, $pinnedCount);
    }
}
