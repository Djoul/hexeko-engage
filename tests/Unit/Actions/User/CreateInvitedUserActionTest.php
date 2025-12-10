<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\InvitedUser\CreateInvitedUserAction;
use App\DTOs\User\CreateInvitedUserDTO;
use App\Events\InvitationCreated;
use App\Exceptions\RoleManagement\UnauthorizedRoleAssignmentException;
use App\Mail\WelcomeEmail;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

/**
 * Tests for unified CreateInvitedUserAction.
 *
 * This test suite consolidates tests from:
 * - CreateInvitedUserActionTest
 * - CreateInvitedUserWithRoleActionTest
 * - CreateUserInvitationActionTest
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('actions')]
class CreateInvitedUserActionTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_an_invited_user_and_sends_welcome_email(): void
    {
        // Arrange
        Mail::fake();
        $financer = ModelFactory::createFinancer();
        $email = 'john.doe.'.uniqid().'@example.com';

        $dto = new CreateInvitedUserDTO(
            first_name: 'John',
            last_name: 'Doe',
            email: $email,
            financer_id: $financer->id,
        );

        $action = new CreateInvitedUserAction($dto);

        // Act
        $invitedUser = $action->execute();

        // Assert
        $this->assertInstanceOf(User::class, $invitedUser);
        $this->assertEquals('John', $invitedUser->first_name);
        $this->assertEquals('Doe', $invitedUser->last_name);
        $this->assertEquals($email, $invitedUser->email);
        $this->assertEquals('pending', $invitedUser->invitation_status);
        $this->assertEquals($financer->id, $invitedUser->invitation_metadata['financer_id'] ?? null);

        // Assert that the welcome email was sent
        Mail::assertSent(WelcomeEmail::class, function ($mail) use ($invitedUser, $email): bool {
            return $mail->invitedUserId === (string) $invitedUser->id &&
                   $mail->user->email === $email;
        });
    }

    #[Test]
    public function it_includes_correct_uuid_link_in_welcome_email(): void
    {
        // Arrange
        Mail::fake();
        $financer = ModelFactory::createFinancer();
        $email = 'john.doe.'.uniqid().'@example.com';

        $dto = new CreateInvitedUserDTO(
            first_name: 'John',
            last_name: 'Doe',
            email: $email,
            financer_id: $financer->id,
        );

        $action = new CreateInvitedUserAction($dto);

        // Act
        $invitedUser = $action->execute();

        // Assert that the welcome email contains the correct UUID link
        Mail::assertSent(WelcomeEmail::class, function ($mail) use ($invitedUser): bool {
            // Build the email to get the view data
            $mailData = $mail->build()->viewData;

            // Check that the URL is correctly formed with the invited user UUID
            $expectedUrl = config('app.front_beneficiary_url').'/invited-user/'.$invitedUser->id;

            return $mailData['url'] === $expectedUrl;
        });
    }

    #[Test]
    public function it_can_create_invitation_without_sending_email(): void
    {
        // Arrange
        Mail::fake();
        $financer = ModelFactory::createFinancer();

        $dto = new CreateInvitedUserDTO(
            first_name: 'Silent',
            last_name: 'User',
            email: 'silent@example.com',
            financer_id: $financer->id,
        );

        $action = new CreateInvitedUserAction($dto);

        // Act
        $invitedUser = $action->withoutEmail()->execute();

        // Assert
        $this->assertInstanceOf(User::class, $invitedUser);
        $this->assertEquals('Silent', $invitedUser->first_name);
        $this->assertEquals('pending', $invitedUser->invitation_status);

        // Assert NO email was sent
        Mail::assertNothingSent();
    }

    #[Test]
    public function it_creates_invited_user_with_role_validation(): void
    {
        Event::fake();

        $financer = ModelFactory::createFinancer();

        // Use unique emails to avoid conflicts with existing test data
        $uniqueId = uniqid();

        // Create inviter with DIVISION_ADMIN role (can assign financer_admin)
        $inviter = ModelFactory::createUser([
            'email' => "inviter.{$uniqueId}@test.com",
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Ensure role exists and assign to inviter
        $team = $inviter->team ?? ModelFactory::createTeam();

        // IMPORTANT: Set team context BEFORE creating/fetching role
        setPermissionsTeamId($team->id);

        Role::firstOrCreate(
            ['name' => 'division_admin', 'guard_name' => 'api', 'team_id' => $team->id],
            ['id' => (string) Str::uuid()]
        );

        $inviter->assignRole('division_admin');

        $dto = new CreateInvitedUserDTO(
            first_name: 'John',
            last_name: 'Doe',
            email: "john.doe.{$uniqueId}@example.com",
            financer_id: $financer->id,
            intended_role: 'financer_admin',
        );

        $action = new CreateInvitedUserAction($dto);
        $result = $action->withRoleValidation($inviter)->execute();

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('John', $result->first_name);
        $this->assertEquals('pending', $result->invitation_status);
        $this->assertEquals('financer_admin', $result->invitation_metadata['intended_role']);
        $this->assertEquals((string) $inviter->id, $result->invited_by);
        $this->assertNotNull($result->invitation_token);
        $this->assertFalse($result->enabled);
        $this->assertNull($result->cognito_id);

        // Verify event was dispatched
        Event::assertDispatched(InvitationCreated::class, function ($event) use ($result): bool {
            return $event->invitedUser->id === $result->id;
        });
    }

    #[Test]
    public function it_validates_inviter_can_assign_role(): void
    {
        $financer = ModelFactory::createFinancer();

        // Create inviter with beneficiary role (cannot assign division_admin)
        $inviter = ModelFactory::createUser([
            'email' => 'inviter@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $dto = new CreateInvitedUserDTO(
            first_name: 'Jane',
            last_name: 'Smith',
            email: 'jane.smith@example.com',
            financer_id: $financer->id,
            intended_role: 'division_admin',
        );

        $this->expectException(UnauthorizedRoleAssignmentException::class);
        $this->expectExceptionMessage('You are not authorized to assign the role: division_admin');

        $action = new CreateInvitedUserAction($dto);
        $action->withRoleValidation($inviter)->execute();
    }

    #[Test]
    public function it_generates_unique_token_for_invitation(): void
    {
        Event::fake();

        $financer = ModelFactory::createFinancer();

        $dto1 = new CreateInvitedUserDTO(
            first_name: 'User',
            last_name: 'One',
            email: 'user1@example.com',
            financer_id: $financer->id,
        );

        $dto2 = new CreateInvitedUserDTO(
            first_name: 'User',
            last_name: 'Two',
            email: 'user2@example.com',
            financer_id: $financer->id,
        );

        $action1 = new CreateInvitedUserAction($dto1);
        $action2 = new CreateInvitedUserAction($dto2);

        $result1 = $action1->withoutEmail()->execute();
        $result2 = $action2->withoutEmail()->execute();

        // Verify both users were created
        $this->assertInstanceOf(User::class, $result1);
        $this->assertInstanceOf(User::class, $result2);
        $this->assertEquals('pending', $result1->invitation_status);
        $this->assertEquals('pending', $result2->invitation_status);

        // Verify tokens are unique
        $this->assertNotNull($result1->invitation_token);
        $this->assertNotNull($result2->invitation_token);
        $this->assertNotEquals($result1->invitation_token, $result2->invitation_token);
    }

    #[Test]
    public function it_prevents_duplicate_pending_invitations_for_same_financer(): void
    {
        $financer = ModelFactory::createFinancer();
        $email = 'duplicate@example.com';

        $dto1 = new CreateInvitedUserDTO(
            first_name: 'First',
            last_name: 'Invite',
            email: $email,
            financer_id: $financer->id,
        );

        // Create first invitation
        $action1 = new CreateInvitedUserAction($dto1);
        $action1->withoutEmail()->execute();

        // Try to create duplicate for SAME financer
        $dto2 = new CreateInvitedUserDTO(
            first_name: 'Second',
            last_name: 'Invite',
            email: $email,
            financer_id: $financer->id,
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('A pending invitation for this email already exists for this financer');

        $action2 = new CreateInvitedUserAction($dto2);
        $action2->withoutEmail()->execute();
    }

    #[Test]
    public function it_allows_same_email_for_different_financers(): void
    {
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();
        $email = 'shared@example.com';

        // Create invitation for first financer
        $dto1 = new CreateInvitedUserDTO(
            first_name: 'User',
            last_name: 'One',
            email: $email,
            financer_id: $financer1->id,
        );

        $action1 = new CreateInvitedUserAction($dto1);
        $user1 = $action1->withoutEmail()->execute();

        // Create invitation with SAME email for DIFFERENT financer - should succeed
        $dto2 = new CreateInvitedUserDTO(
            first_name: 'User',
            last_name: 'Two',
            email: $email,
            financer_id: $financer2->id,
        );

        $action2 = new CreateInvitedUserAction($dto2);
        $user2 = $action2->withoutEmail()->execute();

        // Assert both users were created successfully
        $this->assertInstanceOf(User::class, $user1);
        $this->assertInstanceOf(User::class, $user2);
        $this->assertEquals($email, $user1->email);
        $this->assertEquals($email, $user2->email);
        $this->assertNotEquals($user1->id, $user2->id);

        // Verify each user is attached to their respective financer
        $this->assertTrue($user1->financers->contains($financer1));
        $this->assertTrue($user2->financers->contains($financer2));
    }

    #[Test]
    public function it_can_disable_event_dispatching(): void
    {
        Event::fake();

        $financer = ModelFactory::createFinancer();
        $inviter = ModelFactory::createUser([
            'email' => 'inviter@test.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $dto = new CreateInvitedUserDTO(
            first_name: 'No',
            last_name: 'Event',
            email: 'noevent@example.com',
            financer_id: $financer->id,
        );

        $action = new CreateInvitedUserAction($dto);
        $invitedUser = $action
            ->withRoleValidation($inviter)
            ->withoutEvent()
            ->withoutEmail()
            ->execute();

        $this->assertInstanceOf(User::class, $invitedUser);

        // Assert NO event was dispatched
        Event::assertNotDispatched(InvitationCreated::class);
    }

    #[Test]
    public function it_attaches_financer_with_inactive_status(): void
    {
        $financer = ModelFactory::createFinancer();

        $dto = new CreateInvitedUserDTO(
            first_name: 'Financer',
            last_name: 'Test',
            email: 'financer.test@example.com',
            financer_id: $financer->id,
            sirh_id: 'SIRH123',
        );

        $action = new CreateInvitedUserAction($dto);
        $invitedUser = $action->withoutEmail()->execute();

        // Check financer is attached with active=false
        $this->assertCount(1, $invitedUser->financers);
        $pivot = $invitedUser->financers->first()?->pivot;
        $this->assertNotNull($pivot);
        $this->assertFalse($pivot->active);
        $this->assertEquals('SIRH123', $pivot->sirh_id);
    }

    #[Test]
    public function it_stores_invitation_metadata_correctly(): void
    {
        $financer = ModelFactory::createFinancer();

        $dto = new CreateInvitedUserDTO(
            first_name: 'Meta',
            last_name: 'Test',
            email: 'meta@example.com',
            financer_id: $financer->id,
            intended_role: 'beneficiary',
            external_id: 'EXT456',
            sirh_id: 'SIRH789',
            metadata: ['custom_field' => 'custom_value'],
        );

        $action = new CreateInvitedUserAction($dto);
        $invitedUser = $action->withoutEmail()->execute();

        $this->assertEquals($financer->id, $invitedUser->invitation_metadata['financer_id']);
        $this->assertEquals('EXT456', $invitedUser->invitation_metadata['external_id']);
        $this->assertEquals('SIRH789', $invitedUser->invitation_metadata['sirh_id']);
        $this->assertEquals('beneficiary', $invitedUser->invitation_metadata['intended_role']);
        $this->assertEquals('custom_value', $invitedUser->invitation_metadata['custom_field']);
    }
}
