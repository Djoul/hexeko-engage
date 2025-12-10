<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\HRTools\Actions;

use App\Integrations\HRTools\Actions\ToggleLinkPinAction;
use App\Integrations\HRTools\Database\factories\LinkFactory;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
class ToggleLinkPinActionTest extends TestCase
{
    use DatabaseTransactions;

    private ToggleLinkPinAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ToggleLinkPinAction;
    }

    #[Test]
    public function it_pins_a_link_for_user(): void
    {
        $user = User::factory()->create();
        $link = resolve(LinkFactory::class)->create();

        $result = $this->action->execute($user, $link->id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user->id,
            'link_id' => $link->id,
        ]);
    }

    #[Test]
    public function it_unpins_a_link_for_user(): void
    {
        $user = User::factory()->create();
        $link = resolve(LinkFactory::class)->create();

        // First pin the link
        DB::table('int_outils_rh_link_user')->insert([
            'user_id' => $user->id,
            'link_id' => $link->id,
            'pinned' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = $this->action->execute($user, $link->id);

        $this->assertFalse($result);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user->id,
            'link_id' => $link->id,
            'pinned' => false,
        ]);
    }

    #[Test]
    public function it_toggles_pin_status_correctly(): void
    {
        $user = User::factory()->create();
        $link = resolve(LinkFactory::class)->create();

        // First toggle - should pin
        $result1 = $this->action->execute($user, $link->id);
        $this->assertTrue($result1);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user->id,
            'link_id' => $link->id,
        ]);

        // Second toggle - should unpin
        $result2 = $this->action->execute($user, $link->id);
        $this->assertFalse($result2);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user->id,
            'link_id' => $link->id,
            'pinned' => false,
        ]);

        // Third toggle - should pin again
        $result3 = $this->action->execute($user, $link->id);
        $this->assertTrue($result3);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user->id,
            'link_id' => $link->id,
        ]);
    }

    #[Test]
    public function it_handles_multiple_users_independently(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $link = resolve(LinkFactory::class)->create();

        // User 1 pins the link
        $this->action->execute($user1, $link->id);

        // User 2 pins the same link
        $this->action->execute($user2, $link->id);

        // Both should have the link pinned
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user1->id,
            'link_id' => $link->id,
        ]);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user2->id,
            'link_id' => $link->id,
        ]);

        // User 1 unpins
        $this->action->execute($user1, $link->id);

        // User 1 should have it unpinned, but User 2 should still have it pinned
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user1->id,
            'link_id' => $link->id,
            'pinned' => false,
        ]);
        $this->assertDatabaseHas('int_outils_rh_link_user', [
            'user_id' => $user2->id,
            'link_id' => $link->id,
            'pinned' => true,
        ]);
    }
}
