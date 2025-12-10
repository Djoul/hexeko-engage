<?php

namespace Tests\Unit\Event;

use App\Integrations\HRTools\Events\Metrics\LinkAccessed;
use App\Integrations\HRTools\Events\Metrics\LinkClicked;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('hr-tools')]
#[Group('tool')]
#[Group('engagement')]
#[Group('metrics')]
class ToolEngagementTest extends TestCase
{
    #[Test]
    public function it_logs_tool_clicked_event(): void
    {

        $user = User::factory()->create();
        $link = 'microsoft-teams';

        event(new LinkClicked($user->id, $link));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'LinkClicked',
            'target' => 'link:microsoft-teams',
        ]);
    }

    #[Test]
    public function it_logs_tool_accessed_event(): void
    {

        $user = User::factory()->create();
        $link = 'slack';

        event(new LinkAccessed($user->id, $link));

        $this->artisan('queue:work --once');

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'LinkAccessed',
            'target' => 'link:slack',
        ]);
    }
}
