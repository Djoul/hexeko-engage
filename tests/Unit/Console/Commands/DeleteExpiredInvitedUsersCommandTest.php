<?php

namespace Tests\Unit\Console\Commands;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class DeleteExpiredInvitedUsersCommandTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_deletes_expired_pending_invitations(): void
    {
        // Arrange
        ModelFactory::createFinancer();

        // Create pending invitations with different ages
        User::factory()->create([
            'email' => 'expired.'.uniqid().'@example.com',
            'invitation_status' => 'pending',
            'created_at' => Carbon::now()->subDays(8),
        ]);

        User::factory()->create([
            'email' => 'active.'.uniqid().'@example.com',
            'invitation_status' => 'pending',
            'created_at' => Carbon::now()->subDays(6),
        ]);

        User::factory()->create([
            'email' => 'borderline.'.uniqid().'@example.com',
            'invitation_status' => 'pending',
            'created_at' => Carbon::now()->subDays(7)->subMinutes(1),
        ]);

        // Get counts before running command
        $initialCount = User::where('invitation_status', 'pending')->count();
        $expiredCount = User::where('invitation_status', 'pending')
            ->where('created_at', '<', Carbon::now()->subDays(7))
            ->count();

        // Act
        $this->artisan('invited-users:delete-expired')
            ->expectsOutput('Starting deletion of expired pending invitations...')
            ->assertSuccessful();

        // Assert - Verify count decreased by the number of expired users
        $finalCount = User::where('invitation_status', 'pending')->count();
        $this->assertEquals($initialCount - $expiredCount, $finalCount);
    }

    #[Test]
    public function it_handles_no_expired_pending_invitations(): void
    {
        // Arrange
        ModelFactory::createFinancer();

        User::factory()->create([
            'email' => 'active.'.uniqid().'@example.com',
            'invitation_status' => 'pending',
            'created_at' => Carbon::now()->subDays(6),
        ]);

        User::factory()->create([
            'email' => 'new.'.uniqid().'@example.com',
            'invitation_status' => 'pending',
            'created_at' => Carbon::now()->subDays(1),
        ]);

        $initialCount = User::where('invitation_status', 'pending')->count();

        // Act
        $this->artisan('invited-users:delete-expired')
            ->expectsOutput('No expired pending invitations found.')
            ->assertSuccessful();

        // Assert - Count should remain the same
        $finalCount = User::where('invitation_status', 'pending')->count();
        $this->assertEquals($initialCount, $finalCount);
    }

    #[Test]
    public function it_handles_empty_database(): void
    {
        // Act
        $this->artisan('invited-users:delete-expired')
            ->expectsOutput('No expired pending invitations found.')
            ->assertSuccessful();
    }
}
