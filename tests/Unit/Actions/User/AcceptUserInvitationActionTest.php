<?php

namespace Tests\Unit\Actions\User;

use App\Actions\User\InvitedUser\AcceptUserInvitationAction;
use App\DTOs\User\AcceptUserInvitationDTO;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for AcceptUserInvitationAction.
 * Sprint 2 - Actions: Validates invitation acceptance via Action pattern.
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('actions')]
class AcceptUserInvitationActionTest extends TestCase
{
    use DatabaseTransactions;

    private AcceptUserInvitationAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new AcceptUserInvitationAction;
    }

    #[Test]
    public function it_accepts_valid_invitation_and_activates_user(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $invitedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertEquals('accepted', $user->invitation_status);
        $this->assertTrue($user->enabled);
        $this->assertEquals('cognito-123', $user->cognito_id);
        $this->assertNull($user->invitation_token);
        $this->assertNotNull($user->invitation_accepted_at);
    }

    #[Test]
    public function it_throws_exception_for_invalid_token(): void
    {
        // Arrange
        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => 'invalid-token-12345',
            'cognito_id' => 'cognito-123',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid invitation token');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_for_expired_invitation(): void
    {
        // Arrange
        $expiredUser = User::factory()->invitedExpired()->create();
        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $expiredUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invitation has expired');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_for_already_accepted_invitation(): void
    {
        // Arrange
        $acceptedUser = User::factory()->invitedAccepted()->create();
        // Create a fake token since accepted users have null token
        $acceptedUser->update(['invitation_token' => bin2hex(random_bytes(22))]);

        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $acceptedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invitation already accepted');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_throws_exception_for_revoked_invitation(): void
    {
        // Arrange
        $revokedUser = User::factory()->invitedRevoked()->create();
        // Create a fake token since revoked users have null token
        $revokedUser->update(['invitation_token' => bin2hex(random_bytes(22))]);

        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $revokedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Expect exception
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invitation has been revoked');

        // Act
        $this->action->execute($dto);
    }

    #[Test]
    public function it_sets_acceptance_timestamp(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $invitedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Act
        $before = now()->subSecond();
        $user = $this->action->execute($dto);
        $after = now()->addSecond();

        // Assert
        $this->assertNotNull($user->invitation_accepted_at);
        $this->assertTrue($user->invitation_accepted_at->between($before, $after));
    }

    #[Test]
    public function it_clears_invitation_token_after_acceptance(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $originalToken = $invitedUser->invitation_token;

        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $originalToken,
            'cognito_id' => 'cognito-123',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertNull($user->invitation_token);
        $this->assertNotEquals($originalToken, $user->invitation_token);
    }

    #[Test]
    public function it_enables_user_account_after_acceptance(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $this->assertFalse($invitedUser->enabled);

        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $invitedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertTrue($user->enabled);
    }

    #[Test]
    public function it_persists_cognito_id_to_database(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $invitedUser->invitation_token,
            'cognito_id' => 'cognito-xyz-789',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'cognito_id' => 'cognito-xyz-789',
            'invitation_status' => 'accepted',
            'enabled' => true,
        ]);
    }

    #[Test]
    public function it_validates_state_transition_from_pending_to_accepted(): void
    {
        // Arrange
        $invitedUser = User::factory()->invited()->create();
        $this->assertEquals('pending', $invitedUser->invitation_status);
        $this->assertTrue($invitedUser->canTransitionTo('accepted'));

        $dto = AcceptUserInvitationDTO::fromArray([
            'token' => $invitedUser->invitation_token,
            'cognito_id' => 'cognito-123',
        ]);

        // Act
        $user = $this->action->execute($dto);

        // Assert
        $this->assertEquals('accepted', $user->invitation_status);
        $this->assertFalse($user->canTransitionTo('pending')); // Cannot go back to pending
    }
}
