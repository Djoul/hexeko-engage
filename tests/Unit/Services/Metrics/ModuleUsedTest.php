<?php

namespace Tests\Unit\Services\Metrics;

use App\Events\Metrics\ModuleUsed;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
#[Group('module-metrics')]
class ModuleUsedTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_logs_module_use_as_engagement_event(): void
    {
        $user = User::factory()->create();
        $module = 'wellbeing';

        event(
            new ModuleUsed($user->id, $module)
        );

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'ModuleUsed',
            'target' => $module,
        ]);
    }
}
