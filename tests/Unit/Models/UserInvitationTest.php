<?php

namespace Tests\Unit\Models;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for User Invitation functionality.
 * Sprint 1 - Foundation: Validates invitation fields, scopes, relationships, and state machine.
 */
#[Group('user')]
#[Group('invited-user')]
class UserInvitationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_user_with_invitation_status(): void
    {
        // Arrange & Act
        $inviter = User::factory()->create();
        $user = User::factory()->invited($inviter)->create([
            'email' => 'invited@test.com',
        ]);

        // Assert - 7 assertions as per Sprint 1 plan
        $this->assertEquals('pending', $user->invitation_status);
        $this->assertNotNull($user->invitation_token);
        $this->assertEquals(44, strlen($user->invitation_token));
        $this->assertNotNull($user->invitation_expires_at);
        $this->assertEquals($inviter->id, $user->invited_by);
        $this->assertFalse($user->enabled); // Invited users are disabled
        $this->assertNull($user->cognito_id); // No Cognito ID until acceptance
    }

    #[Test]
    public function it_validates_invitation_status_transitions(): void
    {
        $user = User::factory()->invited()->create();

        // Valid transitions from 'pending'
        $this->assertTrue($user->canTransitionTo('accepted'));
        $this->assertTrue($user->canTransitionTo('expired'));
        $this->assertTrue($user->canTransitionTo('revoked'));

        // Invalid transitions from 'pending'
        $this->assertFalse($user->canTransitionTo('pending'));

        // Test accepted state (no valid transitions)
        $acceptedUser = User::factory()->invitedAccepted()->create();
        $this->assertFalse($acceptedUser->canTransitionTo('pending'));
        $this->assertFalse($acceptedUser->canTransitionTo('expired'));
        $this->assertFalse($acceptedUser->canTransitionTo('revoked'));
    }

    #[Test]
    public function invited_scope_returns_only_pending_invitations(): void
    {
        // Arrange
        $initialCount = User::invited()->count();

        // Create various user types
        $invitedUser1 = User::factory()->invited()->create();
        $invitedUser2 = User::factory()->invited()->create();
        User::factory()->invitedAccepted()->create(); // Should NOT be included
        User::factory()->invitedRevoked()->create(); // Should NOT be included
        User::factory()->create(); // Regular user, should NOT be included

        // Act
        $invitedUsers = User::invited()->get();

        // Assert - count-based assertion
        $this->assertEquals($initialCount + 2, $invitedUsers->count());
        $this->assertTrue($invitedUsers->contains($invitedUser1));
        $this->assertTrue($invitedUsers->contains($invitedUser2));
    }

    #[Test]
    public function active_scope_excludes_pending_invitations(): void
    {
        // Arrange
        $initialCount = User::active()->count();

        // Create users
        User::factory()->invited()->create(); // Should be EXCLUDED
        $activeUser1 = User::factory()->create(); // Regular user
        $acceptedUser = User::factory()->invitedAccepted()->create(); // Previously invited, now active

        // Act
        $activeUsers = User::active()->get();

        // Assert
        $this->assertEquals($initialCount + 2, $activeUsers->count());
        $this->assertTrue($activeUsers->contains($activeUser1));
        $this->assertTrue($activeUsers->contains($acceptedUser));
    }

    #[Test]
    public function expired_invitations_scope_identifies_old_tokens(): void
    {
        // Arrange
        $initialCount = User::expiredInvitations()->count();

        // Create users
        $expiredUser = User::factory()->invitedExpired()->create();
        User::factory()->invited()->create(); // Not expired, should be EXCLUDED

        // Act
        $expiredInvitations = User::expiredInvitations()->get();

        // Assert
        $this->assertEquals($initialCount + 1, $expiredInvitations->count());
        $this->assertTrue($expiredInvitations->contains($expiredUser));
        $this->assertTrue($expiredUser->isInvitationExpired());
    }

    #[Test]
    public function inviter_relationship_retrieves_user_who_invited(): void
    {
        // Arrange
        $inviter = User::factory()->create([
            'email' => 'inviter@test.com',
            'first_name' => 'John',
            'last_name' => 'Inviter',
        ]);

        $invitedUser = User::factory()->invited($inviter)->create();

        // Act
        $retrievedInviter = $invitedUser->inviter;

        // Assert
        $this->assertNotNull($retrievedInviter);
        $this->assertEquals($inviter->id, $retrievedInviter->id);
        $this->assertEquals('inviter@test.com', $retrievedInviter->email);
        $this->assertEquals('John', $retrievedInviter->first_name);
    }

    #[Test]
    public function invited_users_relationship_retrieves_all_invited_by_user(): void
    {
        // Arrange
        $inviter = User::factory()->create([
            'email' => 'inviter@test.com',
        ]);

        // Create 3 users invited by this inviter
        $invited1 = User::factory()->invited($inviter)->create();
        $invited2 = User::factory()->invited($inviter)->create();
        $invited3 = User::factory()->invited($inviter)->create();

        // Create a user invited by someone else (should NOT be included)
        $otherInviter = User::factory()->create();
        $otherInvited = User::factory()->invited($otherInviter)->create();

        // Act
        $invitedUsers = $inviter->invitedUsers;

        // Assert - Relationship returns exactly 3 users invited by this inviter
        $this->assertCount(3, $invitedUsers);
        $this->assertTrue($invitedUsers->contains($invited1));
        $this->assertTrue($invitedUsers->contains($invited2));
        $this->assertTrue($invitedUsers->contains($invited3));
        $this->assertFalse($invitedUsers->contains($otherInvited));
    }

    #[Test]
    public function is_invited_user_returns_true_for_pending_invitations(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $regularUser = User::factory()->create();
        $acceptedUser = User::factory()->invitedAccepted()->create();

        // Assert
        $this->assertTrue($invitedUser->isInvitedUser());
        $this->assertFalse($regularUser->isInvitedUser());
        $this->assertFalse($acceptedUser->isInvitedUser());
    }

    #[Test]
    public function is_invitation_expired_detects_expired_tokens(): void
    {
        // Arrange
        $expiredUser = User::factory()->invitedExpired()->create();
        $validUser = User::factory()->invited()->create();

        // Assert
        $this->assertTrue($expiredUser->isInvitationExpired());
        $this->assertFalse($validUser->isInvitationExpired());
    }

    #[Test]
    public function has_valid_invitation_checks_pending_and_not_expired(): void
    {
        // Arrange
        $validInvitation = User::factory()->invited()->create();
        $expiredInvitation = User::factory()->invitedExpired()->create();
        $acceptedUser = User::factory()->invitedAccepted()->create();

        // Assert
        $this->assertTrue($validInvitation->hasValidInvitation());
        $this->assertFalse($expiredInvitation->hasValidInvitation());
        $this->assertFalse($acceptedUser->hasValidInvitation());
    }

    #[Test]
    public function to_invited_user_array_returns_compatible_format(): void
    {
        // Arrange
        $user = User::factory()->invited()->create([
            'email' => 'test@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Act
        $array = $user->toInvitedUserArray();

        // Assert - Verify InvitedUser-compatible structure
        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('email', $array);
        $this->assertArrayHasKey('first_name', $array);
        $this->assertArrayHasKey('last_name', $array);
        $this->assertArrayHasKey('invitation_token', $array);
        $this->assertArrayHasKey('expires_at', $array);
        $this->assertArrayHasKey('extra_data', $array);

        $this->assertEquals('test@example.com', $array['email']);
        $this->assertEquals('John', $array['first_name']);
        $this->assertEquals('Doe', $array['last_name']);
    }

    #[Test]
    public function invitation_metadata_is_properly_cast_to_array(): void
    {
        // Arrange
        $metadata = [
            'source' => 'csv_import',
            'import_batch_id' => 'batch_123',
            'custom_field' => 'value',
        ];

        $user = User::factory()->invited()->create([
            'invitation_metadata' => $metadata,
        ]);

        // Act
        $user->refresh();

        // Assert
        $this->assertIsArray($user->invitation_metadata);
        $this->assertEquals('csv_import', $user->invitation_metadata['source']);
        $this->assertEquals('batch_123', $user->invitation_metadata['import_batch_id']);
        $this->assertEquals('value', $user->invitation_metadata['custom_field']);
    }

    #[Test]
    public function invited_by_scope_filters_by_inviter(): void
    {
        // Arrange
        $inviter1 = User::factory()->create(['email' => 'inviter1@test.com']);
        $inviter2 = User::factory()->create(['email' => 'inviter2@test.com']);

        $user1 = User::factory()->invited($inviter1)->create();
        $user2 = User::factory()->invited($inviter1)->create();
        $user3 = User::factory()->invited($inviter2)->create();

        // Act
        $invitedByInviter1 = User::invitedBy($inviter1->id)->get();

        // Assert
        $this->assertCount(2, $invitedByInviter1);
        $this->assertTrue($invitedByInviter1->contains($user1));
        $this->assertTrue($invitedByInviter1->contains($user2));
        $this->assertFalse($invitedByInviter1->contains($user3));
    }
}
