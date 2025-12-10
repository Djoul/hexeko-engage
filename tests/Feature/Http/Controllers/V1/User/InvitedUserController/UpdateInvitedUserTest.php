<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UpdateInvitedUserTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/users';

    #[Test]
    public function it_can_update_an_invited_user(): void
    {
        // Create financer for the invited user
        $financer = ModelFactory::createFinancer();

        // Create an invited user (User with invitation_status='pending')
        $invitedUser = ModelFactory::createUser([
            'first_name' => 'Original',
            'last_name' => 'Name',
            'email' => 'original@example.com',
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // Data to update the invited user
        // Note: financer_id is required by validation but not actually updateable (ignored by service)
        $updateData = [
            'id' => $invitedUser->id,
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'updated@example.com',
            'financer_id' => $financer->id,
        ];

        // Make a PUT request to the update endpoint
        $response = $this->putJson(self::URI.'/'.$invitedUser->id, $updateData);

        // Assert that the response is successful
        $response->assertStatus(200);

        // Assert that the database was updated
        $this->assertDatabaseHas('users', [
            'id' => $invitedUser->id,
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'updated@example.com',
            'invitation_status' => 'pending',
        ]);
    }

    #[Test]
    public function it_returns_404_when_invited_user_not_found(): void
    {
        // Generate a random UUID that doesn't exist in the database
        $nonExistentUuid = '123e4567-e89b-12d3-a456-426614174000';

        // Data to update the invited user
        $updateData = [
            'id' => $nonExistentUuid,
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'updated@example.com',
            'financer_id' => ModelFactory::createFinancer()->id,
        ];

        // Make a PUT request to the update endpoint with a non-existent UUID
        $response = $this->putJson(self::URI.'/'.$nonExistentUuid, $updateData);

        // Assert that the response is a 404 Not Found
        $response->assertStatus(404);

        // Assert that the response contains the expected error message
        $response->assertJson([
            'message' => 'User not found',
        ]);
    }

    #[Test]
    public function it_validates_email_format(): void
    {
        // Create financer for the invited user
        $financer = ModelFactory::createFinancer();

        // Create an invited user (User with invitation_status='pending')
        $invitedUser = ModelFactory::createUser([
            'email' => 'test@example.com',
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer, 'active' => false],
            ],
        ]);

        // Data with invalid email format
        $updateData = [
            'first_name' => 'Updated',
            'last_name' => 'User',
            'email' => 'invalid-email', // Invalid email format
            'financer_id' => $financer->id,
        ];

        // Make a PUT request to the update endpoint
        $response = $this->putJson(self::URI.'/'.$invitedUser->id, $updateData);

        // Assert that the response is a 422 Unprocessable Entity
        $response->assertStatus(422);

        // Assert that the response contains validation errors for the email field
        $response->assertJsonValidationErrors(['email']);
    }
}
