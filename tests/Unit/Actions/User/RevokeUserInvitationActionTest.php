<?php

namespace Tests\Unit\Actions\User;

use App\Actions\User\InvitedUser\RevokeUserInvitationAction;
use App\DTOs\User\RevokeUserInvitationDTO;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for RevokeUserInvitationAction.
 * Sprint 2 - Actions: Validates invitation revocation via Action pattern.
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('actions')]
class RevokeUserInvitationActionTest extends TestCase
{
    use DatabaseTransactions;

    private RevokeUserInvitationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RevokeUserInvitationAction;
    }

    #[Test]
    public function it_revokes_pending_invitation(): void
    {
        // Arrange
        $inviter = User::factory()->create();
        $invitedUser = User::factory()->invited($inviter)->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => $inviter->id,
            'reason' => 'User requested cancellation',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertEquals('revoked', $user->invitation_status);
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->invitation_metadata);
    }

    #[Test]
    public function it_throws_exception_for_invalid_user_id(): void
    {
        // Arrange - Use a valid UUID format but non-existent ID
        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => '00000000-0000-0000-0000-000000000000',
            'revoked_by' => '00000000-0000-0000-0000-000000000001',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('User not found');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_for_already_accepted_invitation(): void
    {
        // Arrange
        $acceptedUser = User::factory()->invitedAccepted()->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $acceptedUser->id,
            'revoked_by' => 'admin-id',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot revoke accepted invitation');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_for_already_revoked_invitation(): void
    {
        // Arrange
        $revokedUser = User::factory()->invitedRevoked()->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $revokedUser->id,
            'revoked_by' => 'admin-id',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invitation already revoked');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_clears_invitation_token_after_revocation(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $originalToken = $invitedUser->invitation_token;
        $this->assertNotNull($originalToken);

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => 'admin-id',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertNull($user->invitation_token);
        $this->assertNotEquals($originalToken, $user->invitation_token);
    }

    #[Test]
    public function it_stores_revocation_metadata(): void
    {
        // Arrange
        $inviter = User::factory()->create();
        $invitedUser = User::factory()->invited($inviter)->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => $inviter->id,
            'reason' => 'Position filled',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertNotNull($user->invitation_metadata);
        $this->assertArrayHasKey('revoked_at', $user->invitation_metadata);
        $this->assertArrayHasKey('revoked_by', $user->invitation_metadata);
        $this->assertArrayHasKey('revoked_reason', $user->invitation_metadata);
        $this->assertEquals($inviter->id, $user->invitation_metadata['revoked_by']);
        $this->assertEquals('Position filled', $user->invitation_metadata['revoked_reason']);
    }

    #[Test]
    public function it_stores_revocation_metadata_without_reason(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => 'admin-id',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertNotNull($user->invitation_metadata);
        $this->assertArrayHasKey('revoked_at', $user->invitation_metadata);
        $this->assertArrayHasKey('revoked_by', $user->invitation_metadata);
        $this->assertArrayNotHasKey('revoked_reason', $user->invitation_metadata);
    }

    #[Test]
    public function it_persists_revocation_to_database(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => 'admin-id',
            'reason' => 'Test revocation',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'invitation_status' => 'revoked',
            'invitation_token' => null,
        ]);
    }

    #[Test]
    public function it_validates_state_transition_from_pending_to_revoked(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $this->assertEquals('pending', $invitedUser->invitation_status);
        $this->assertTrue($invitedUser->canTransitionTo('revoked'));

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $invitedUser->id,
            'revoked_by' => 'admin-id',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertEquals('revoked', $user->invitation_status);
        $this->assertFalse($user->canTransitionTo('pending')); // Cannot go back to pending
        $this->assertFalse($user->canTransitionTo('accepted')); // Cannot accept after revoked
    }

    #[Test]
    public function it_allows_revoking_expired_invitations(): void
    {
        // Arrange
        $expiredUser = User::factory()->invitedExpired()->create();
        $this->assertTrue($expiredUser->isInvitationExpired());

        $dto = RevokeUserInvitationDTO::fromArray([
            'user_id' => $expiredUser->id,
            'revoked_by' => 'admin-id',
            'reason' => 'Cleanup expired invitations',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertEquals('revoked', $user->invitation_status);
        $this->assertEquals('Cleanup expired invitations', $user->invitation_metadata['revoked_reason']);
    }
}
