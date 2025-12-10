<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserProfileImageController;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('media')]
class UserProfileImageUpdateTest extends ProtectedRouteTestCase
{
    protected bool $checkAuth = false;

    protected bool $checkPermissions = false;

    #[Test]
    public function it_can_update_user_profile_image(): void
    {
        // Create a user with proper financer relationship
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'status' => 'active',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Financer',
            'status' => 'active',
        ]);

        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Test the route
        $response = $this->actingAs($user)
            ->postJson('/api/v1/user/profile-image', [
                'profile_image' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==',
            ]);

        $response->assertOk()
            ->assertJson(['message' => 'Profile image updated']);
    }

    #[Test]
    public function it_validates_profile_image_is_required(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'financers' => [['financer' => $financer, 'active' => true]],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/user/profile-image', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_image']);
    }

    #[Test]
    public function it_validates_profile_image_is_string(): void
    {
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'financers' => [['financer' => $financer, 'active' => true]],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/api/v1/user/profile-image', [
                'profile_image' => 123,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['profile_image']);
    }
}
