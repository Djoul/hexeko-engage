<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class DeleteInvitedUserTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/users';

    #[Test]
    public function it_can_delete_an_invited_user(): void
    {
        // Create a financer for the invited user
        $financer = ModelFactory::createFinancer();

        // Create an invited user (User with invitation_status='pending')
        $invitedUser = ModelFactory::createUser([
            'email' => 'invited@test.com',
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // Make a DELETE request to the destroy endpoint
        $response = $this->deleteJson(self::URI.'/'.$invitedUser->id);

        // Assert that the response is successful
        $response->assertStatus(204);

        // Assert that the invited user was soft deleted (User model uses SoftDeletes)
        $this->assertSoftDeleted('users', [
            'id' => $invitedUser->id,
        ]);
    }

    #[Test]
    public function it_returns_404_when_invited_user_not_found(): void
    {
        // Generate a random UUID that doesn't exist in the database
        $nonExistentUuid = '123e4567-e89b-12d3-a456-426614174000';

        // Make a DELETE request to the destroy endpoint with a non-existent UUID
        $response = $this->deleteJson(self::URI.'/'.$nonExistentUuid);

        // Assert that the response is a 404 Not Found
        $response->assertStatus(404);

        // Assert that the response contains the expected error message
        $response->assertJson([
            'message' => 'User not found',
        ]);
    }
}
