<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User\Merge;

use App\Actions\User\Merge\MergeUserAction;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('actions')]
#[Group('merge')]
class MergeUserActionTest extends TestCase
{
    use DatabaseTransactions;

    private MergeUserAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(MergeUserAction::class);
    }

    #[Test]
    public function it_merges_invited_user_into_existing_user(): void
    {
        // Arrange - Create division first, then financers from same division
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);

        // Create invited user with financer
        $invitedUser = ModelFactory::createUser([
            'email' => 'invited@test.com',
            'first_name' => 'Invited',
            'last_name' => 'User',
            'invitation_status' => 'pending',
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'sirh_id' => 'SIRH123',
            'role' => 'beneficiary',
        ]);

        // Create existing user with a default financer from same division
        $existingUserFinancer = ModelFactory::createFinancer([
            'name' => 'Existing User Financer',
            'division_id' => $division->id,
        ]);
        $existingUser = ModelFactory::createUser([
            'email' => 'existing@test.com',
            'first_name' => 'Existing',
            'last_name' => 'User',
            'invitation_status' => null,
            'financers' => [
                ['financer' => $existingUserFinancer, 'active' => true],
            ],
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($existingUser->id, $result->id);
        $this->assertEquals('Existing', $result->first_name);

        // Verify both financers are now attached (invited financer + existing financer)
        $this->assertTrue($result->relationLoaded('financers'));
        $this->assertCount(2, $result->financers);

        // Verify invited user's financer was transferred and activated
        $transferredFinancer = $result->financers->firstWhere('id', $financer->id);
        $this->assertNotNull($transferredFinancer);
        $this->assertTrue((bool) $transferredFinancer->pivot->active);
        $this->assertEquals('SIRH123', $transferredFinancer->pivot->sirh_id);

        // Verify invited user was soft deleted
        $this->assertSoftDeleted('users', [
            'id' => $invitedUser->id,
        ]);
    }

    #[Test]
    public function it_activates_all_transferred_financers(): void
    {
        // Arrange - Create division first, then financers from same division
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer1 = ModelFactory::createFinancer(['name' => 'Financer 1', 'division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['name' => 'Financer 2', 'division_id' => $division->id]);

        // Create invited user with multiple financers (all inactive)
        $invitedUser = ModelFactory::createUser([
            'email' => 'multi-financer@test.com',
            'invitation_status' => 'pending',
        ]);

        $invitedUser->financers()->attach($financer1->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);
        $invitedUser->financers()->attach($financer2->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Create existing user
        $existingUser = ModelFactory::createUser([
            'email' => 'existing-multi@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(2, $result->financers);

        // Verify all financers are activated
        foreach ($result->financers as $financer) {
            $this->assertTrue((bool) $financer->pivot->active);
        }
    }

    #[Test]
    public function it_preserves_existing_user_financers(): void
    {
        // Arrange - Create division first, then financers from same division
        $division = ModelFactory::createDivision(['name' => 'Test Division']);
        $financer1 = ModelFactory::createFinancer(['name' => 'Existing Financer', 'division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['name' => 'Invited Financer', 'division_id' => $division->id]);

        // Create invited user with financer
        $invitedUser = ModelFactory::createUser([
            'email' => 'invited-preserve@test.com',
            'invitation_status' => 'pending',
        ]);
        $invitedUser->financers()->attach($financer2->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Create existing user with existing financer
        $existingUser = ModelFactory::createUser([
            'email' => 'existing-preserve@test.com',
        ]);
        $existingUser->financers()->attach($financer1->id, [
            'active' => true,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(2, $result->financers);

        // Verify both financers are present
        $financerIds = $result->financers->pluck('id')->toArray();
        $this->assertContains($financer1->id, $financerIds);
        $this->assertContains($financer2->id, $financerIds);
    }

    #[Test]
    public function it_preserves_pivot_data_during_merge(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $fromDate = now()->subDays(30);
        $sirhId = 'CUSTOM-SIRH-ID';

        // Create invited user with specific pivot data
        $invitedUser = ModelFactory::createUser([
            'email' => 'pivot@test.com',
            'invitation_status' => 'pending',
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => $fromDate,
            'sirh_id' => $sirhId,
            'role' => 'beneficiary',
        ]);

        // Create existing user
        $existingUser = ModelFactory::createUser([
            'email' => 'existing-pivot@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $transferredFinancer = $result->financers->first();
        $this->assertEquals($sirhId, $transferredFinancer->pivot->sirh_id);
        $this->assertEquals($fromDate->toDateString(), $transferredFinancer->pivot->from->toDateString());
    }

    #[Test]
    public function it_throws_exception_when_invited_user_not_found(): void
    {
        // Arrange
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $existingUser = ModelFactory::createUser(['email' => 'exists@test.com']);

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Invited user not found');

        $this->action->execute($nonExistentId, $existingUser->email);
    }

    #[Test]
    public function it_throws_exception_when_user_is_not_pending_invitation(): void
    {
        // Arrange
        $regularUser = ModelFactory::createUser([
            'email' => 'regular@test.com',
            'invitation_status' => null, // Not pending
        ]);

        $existingUser = ModelFactory::createUser(['email' => 'existing@test.com']);

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('Invited user not found');

        $this->action->execute($regularUser->id, $existingUser->email);
    }

    #[Test]
    public function it_throws_exception_when_existing_user_not_found(): void
    {
        // Arrange
        $invitedUser = ModelFactory::createUser([
            'email' => 'invited@test.com',
            'invitation_status' => 'pending',
        ]);

        $nonExistentEmail = 'doesnotexist@test.com';

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage("User not found with email: {$nonExistentEmail}");

        $this->action->execute($invitedUser->id, $nonExistentEmail);
    }

    #[Test]
    public function it_throws_exception_when_target_user_is_pending_invitation(): void
    {
        // Arrange
        $invitedUser1 = ModelFactory::createUser([
            'email' => 'invited1@test.com',
            'invitation_status' => 'pending',
        ]);

        $invitedUser2 = ModelFactory::createUser([
            'email' => 'invited2@test.com',
            'invitation_status' => 'pending', // Also pending - invalid target
        ]);

        // Act & Assert
        $this->expectException(ModelNotFoundException::class);
        $this->expectExceptionMessage('User not found with email: invited2@test.com');

        $this->action->execute($invitedUser1->id, $invitedUser2->email);
    }

    #[Test]
    public function it_handles_invited_user_with_no_financers(): void
    {
        // Arrange
        $invitedUser = ModelFactory::createUser([
            'email' => 'no-financers@test.com',
            'invitation_status' => 'pending',
        ]);

        $existingUser = ModelFactory::createUser([
            'email' => 'existing-no-financers@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($existingUser->id, $result->id);

        // Verify invited user was still deleted
        $this->assertSoftDeleted('users', [
            'id' => $invitedUser->id,
        ]);
    }

    #[Test]
    public function it_returns_user_with_loaded_relationships(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $invitedUser = ModelFactory::createUser([
            'email' => 'relations@test.com',
            'invitation_status' => 'pending',
        ]);
        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        $existingUser = ModelFactory::createUser([
            'email' => 'existing-relations@test.com',
        ]);

        // Create role for existing user
        $role = ModelFactory::createRole(['name' => 'test-merge-role']);
        $existingUser->assignRole($role);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Verify all relationships are loaded
        $this->assertTrue($result->relationLoaded('financers'));
        $this->assertTrue($result->relationLoaded('roles'));
        $this->assertTrue($result->relationLoaded('permissions'));
    }

    #[Test]
    public function it_wraps_merge_in_transaction(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $invitedUser = ModelFactory::createUser([
            'email' => 'transaction@test.com',
            'invitation_status' => 'pending',
        ]);
        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'role' => 'beneficiary',
        ]);

        $existingUser = ModelFactory::createUser([
            'email' => 'existing-transaction@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert - All changes should be committed atomically
        $this->assertInstanceOf(User::class, $result);

        // Verify financer was transferred
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $existingUser->id,
            'financer_id' => $financer->id,
            'active' => true,
        ]);

        // Verify invited user was deleted
        $this->assertSoftDeleted('users', [
            'id' => $invitedUser->id,
        ]);
    }

    #[Test]
    public function it_sets_default_from_date_if_missing(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // Create invited user WITHOUT from date in pivot
        $invitedUser = ModelFactory::createUser([
            'email' => 'no-from@test.com',
            'invitation_status' => 'pending',
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => null, // No from date
            'role' => 'beneficiary',
        ]);

        $existingUser = ModelFactory::createUser([
            'email' => 'existing-no-from@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $transferredFinancer = $result->financers->first();
        $this->assertNotNull($transferredFinancer->pivot->from);
    }

    #[Test]
    public function it_sets_empty_sirh_id_if_missing(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // Create invited user WITHOUT sirh_id in pivot
        $invitedUser = ModelFactory::createUser([
            'email' => 'no-sirh@test.com',
            'invitation_status' => 'pending',
        ]);

        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'sirh_id' => null, // No SIRH ID
            'role' => 'beneficiary',
        ]);

        $existingUser = ModelFactory::createUser([
            'email' => 'existing-no-sirh@test.com',
        ]);

        // Act
        $result = $this->action->execute($invitedUser->id, $existingUser->email);

        // Assert
        $transferredFinancer = $result->financers->first();
        $this->assertEquals('', $transferredFinancer->pivot->sirh_id);
    }
}
