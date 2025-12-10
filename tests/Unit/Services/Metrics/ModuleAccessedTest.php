<?php

namespace Tests\Unit\Services\Metrics;

use App\Events\Metrics\ModuleAccessed;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('module')]
#[Group('module-metrics')]
class ModuleAccessedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_logs_module_access_as_engagement_event(): void
    {
        $user = User::factory()->create();
        $module = 'communication-rh';

        event(new ModuleAccessed($user->id, $module));

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'ModuleAccessed',
            'target' => $module,
        ]);
    }
}
