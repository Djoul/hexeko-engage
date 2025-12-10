<?php

namespace Tests\Unit\Services\Metrics;

use App\Events\Metrics\UserAccountActivated;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('metrics')]
class AccountActivatedTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_logs_account_activation_as_engagement_event(): void
    {
        $user = User::factory()->create();

        event(new UserAccountActivated($user->id));

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'UserAccountActivated',
        ]);
    }
}
