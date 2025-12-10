<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User\InvitedUser;

use App\Actions\User\InvitedUser\ShowInvitedUserAction;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('actions')]
#[Group('invited-user')]
class ShowInvitedUserActionTest extends TestCase
{
    use DatabaseTransactions;

    private ShowInvitedUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = new ShowInvitedUserAction;
    }

    #[Test]
    public function it_retrieves_invited_user_with_financer_from_metadata(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);

        $invitedUser = ModelFactory::createUser([
            'first_name' => 'Invited',
            'last_name' => 'User',
            'email' => 'invited@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
                'intended_role' => 'beneficiary',
            ],
        ]);

        // Manually attach financer with complete pivot data including required role
        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('financer', $result);

        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals($invitedUser->id, $result['user']->id);
        $this->assertEquals('Invited', $result['user']->first_name);
        $this->assertEquals('pending', $result['user']->invitation_status);

        $this->assertInstanceOf(Financer::class, $result['financer']);
        $this->assertEquals($financer->id, $result['financer']->id);
        $this->assertEquals('Test Financer', $result['financer']->name);
    }

    #[Test]
    public function it_retrieves_invited_user_with_default_relations(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $invitedUser = ModelFactory::createUser([
            'email' => 'invited-relations@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);

        // Verify default relation is loaded
        $this->assertTrue($result['user']->relationLoaded('financers'));
    }

    #[Test]
    public function it_retrieves_invited_user_with_custom_relations(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $invitedUser = ModelFactory::createUser([
            'email' => 'invited-custom@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, ['media']);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);

        // Verify custom relation is loaded
        $this->assertTrue($result['user']->relationLoaded('media'));

        // Verify default relation is NOT loaded (custom provided)
        $this->assertFalse($result['user']->relationLoaded('financers'));
    }

    #[Test]
    public function it_returns_null_financer_when_no_financer_id_in_metadata(): void
    {
        // Arrange
        $invitedUser = ModelFactory::createUser([
            'email' => 'no-financer@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'intended_role' => 'beneficiary',
            ],
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNull($result['financer']);
    }

    #[Test]
    public function it_returns_null_financer_when_metadata_is_null(): void
    {
        // Arrange
        $invitedUser = ModelFactory::createUser([
            'email' => 'null-metadata@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => null,
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNull($result['financer']);
    }

    #[Test]
    public function it_returns_null_financer_when_financer_not_found(): void
    {
        // Arrange
        $nonExistentFinancerId = '00000000-0000-0000-0000-000000000000';

        $invitedUser = ModelFactory::createUser([
            'email' => 'invalid-financer@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $nonExistentFinancerId,
            ],
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertNull($result['financer']);
    }

    #[Test]
    public function it_excludes_non_invited_users(): void
    {
        // Arrange - Create regular user (invitation_status = null)
        $regularUser = ModelFactory::createUser([
            'email' => 'regular@test.com',
            'invitation_status' => null,
        ]);

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Invited user not found');

        $this->action->execute($regularUser->id);
    }

    #[Test]
    public function it_retrieves_users_with_accepted_invitation_status(): void
    {
        // Arrange - Create user with accepted invitation
        $financer = ModelFactory::createFinancer(['name' => 'Test Financer']);

        $acceptedUser = ModelFactory::createUser([
            'email' => 'accepted@test.com',
            'invitation_status' => 'accepted',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $acceptedUser->financers()->attach($financer->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($acceptedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('financer', $result);

        $this->assertInstanceOf(User::class, $result['user']);
        $this->assertEquals($acceptedUser->id, $result['user']->id);
        $this->assertEquals('accepted', $result['user']->invitation_status);

        $this->assertInstanceOf(Financer::class, $result['financer']);
        $this->assertEquals($financer->id, $result['financer']->id);
    }

    #[Test]
    public function it_throws_exception_for_non_existent_user(): void
    {
        // Arrange
        $nonExistentId = '00000000-0000-0000-0000-000000000000';

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Invited user not found');

        $this->action->execute($nonExistentId);
    }

    #[Test]
    public function it_handles_empty_relations_array(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $invitedUser = ModelFactory::createUser([
            'email' => 'empty-relations@test.com',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act - empty array should use defaults
        $result = $this->action->execute($invitedUser->id, []);

        // Assert
        $this->assertIsArray($result);
        $this->assertInstanceOf(User::class, $result['user']);

        // Verify default relation is loaded (empty array = use defaults)
        $this->assertTrue($result['user']->relationLoaded('financers'));
    }

    #[Test]
    public function it_retrieves_users_with_revoked_invitation_status(): void
    {
        // Arrange - Create user with revoked invitation
        $financer = ModelFactory::createFinancer();

        $revokedUser = ModelFactory::createUser([
            'email' => 'revoked@test.com',
            'invitation_status' => 'revoked',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $revokedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($revokedUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals('revoked', $result['user']->invitation_status);
    }

    #[Test]
    public function it_retrieves_users_with_expired_invitation(): void
    {
        // Arrange - Create user with expired invitation
        $financer = ModelFactory::createFinancer();

        $expiredUser = ModelFactory::createUser([
            'email' => 'expired@test.com',
            'invitation_status' => 'pending',
            'invitation_expires_at' => now()->subDays(1), // Expired yesterday
            'invitation_metadata' => [
                'financer_id' => $financer->id,
            ],
        ]);

        $expiredUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($expiredUser->id);

        // Assert
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertEquals($expiredUser->id, $result['user']->id);
        $this->assertTrue($result['user']->isInvitationExpired());
    }
}
