<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users', 'permissions', 'model_has_permissions'], scope: 'class')]
#[Group('user')]
class InvitedUserControllerTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_stores_invited_user_successfully(): void
    {
        // Arrange
        // Create user with FINANCER_SUPER_ADMIN role to have the necessary permissions
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        $financer = ModelFactory::createFinancer();

        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'financer_id' => $financer->id,
            'phone' => '+33612345678',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/invited-users', $userData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'invitation_status',
                    'invitation_metadata',
                    'created_at',
                    'updated_at',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'invitation_status' => 'pending',
        ]);
    }

    #[Test]
    public function it_fails_to_store_invited_user_with_invalid_data(): void
    {
        // Arrange
        // Create user with FINANCER_SUPER_ADMIN role to have the necessary permissions
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Missing required fields
        $userData = [
            'first_name' => 'John',
            // Missing last_name
            'email' => 'invalid-email', // Invalid email format
            // Missing financer_id
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/invited-users', $userData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name', 'email', 'financer_id']);

        $this->assertDatabaseMissing('users', [
            'first_name' => 'John',
            'email' => 'invalid-email',
            'invitation_status' => 'pending',
        ]);
    }

    #[Test]
    public function it_fails_to_store_invited_user_when_no_financer_provided(): void
    {
        // Arrange
        // Create user with FINANCER_SUPER_ADMIN role to have the necessary permissions
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Create user without any financer relationship and don't provide financer_id
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '+33612345678',
            // No financer_id provided
        ];

        // Act - Don't provide x-financer-id header and don't add financer to user
        $response = $this->actingAs($user)
            ->postJson('/api/v1/invited-users', $userData);

        // Assert - Should fail validation since financer_id is required
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_fails_to_store_invited_user_with_invalid_financer_id(): void
    {
        // Arrange
        // Create user with FINANCER_SUPER_ADMIN role to have the necessary permissions
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        // Create user data with invalid financer_id
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'financer_id' => '123e4567-e89b-12d3-a456-426614174000', // Valid UUID but doesn't exist
            'phone' => '+33612345678',
        ];

        // Act
        $response = $this->actingAs($user)
            ->postJson('/api/v1/invited-users', $userData);

        // Assert - Should fail validation since financer_id doesn't exist
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['financer_id']);
    }

    #[Test]
    public function it_shows_invited_user_with_resource_format(): void
    {
        // Arrange
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);

        $financer = ModelFactory::createFinancer([
            'available_languages' => ['fr-BE', 'en-GB'],
        ]);

        // Create invited user manually with pending status
        $invitedUser = ModelFactory::createUser([
            'first_name' => 'Kevin',
            'last_name' => 'Dessouroux',
            'email' => 'kevin.dessouroux@hexeko.com',
            'phone' => '+32123456789',
            'invitation_status' => 'pending',
            'invitation_metadata' => [
                'financer_id' => $financer->id,
                'intended_role' => 'beneficiary',
                'sirh_id' => 'SIRH-123',
            ],
        ]);

        // Attach financer relation
        $invitedUser->financers()->attach($financer->id, [
            'active' => false,
            'from' => now(),
            'language' => 'fr-BE',
        ]);

        // Act
        $response = $this->actingAs($user)
            ->getJson("/api/v1/invited-users/{$invitedUser->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'phone',
                    'sirh_id',
                    'invitation_status',
                    'invitation_expires_at',
                    'invitation_accepted_at',
                    'is_expired',
                    'financers',
                    'created_at',
                    'updated_at',
                ],
                'meta' => [
                    'available_languages_as_select_array',
                ],
            ]);

        // Verify data values
        $data = $response->json('data');
        $this->assertEquals($invitedUser->id, $data['id']);
        $this->assertEquals('Kevin', $data['first_name']);
        $this->assertEquals('Dessouroux', $data['last_name']);
        $this->assertEquals('kevin.dessouroux@hexeko.com', $data['email']);
        $this->assertEquals('+32123456789', $data['phone']);
        $this->assertEquals('SIRH-123', $data['sirh_id']);
        $this->assertEquals('pending', $data['invitation_status']);
        $this->assertFalse($data['is_expired']);

        // Verify financers array (new format)
        $this->assertIsArray($data['financers']);
        $this->assertCount(1, $data['financers']);
        $this->assertEquals($financer->id, $data['financers'][0]['id']);
        $this->assertEquals($financer->name, $data['financers'][0]['name']);
        $this->assertEquals('inactive', $data['financers'][0]['status']);
        $this->assertEquals('fr-BE', $data['financers'][0]['language']);

        // Verify meta
        $meta = $response->json('meta');
        $this->assertArrayHasKey('available_languages_as_select_array', $meta);
        $this->assertCount(2, $meta['available_languages_as_select_array']);
    }

    #[Test]
    public function it_shows_accepted_invitation_with_status_fields(): void
    {
        // Arrange
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financer = ModelFactory::createFinancer();

        $acceptedUser = ModelFactory::createUser([
            'email' => 'accepted@test.com',
            'invitation_status' => 'accepted',
            'invitation_accepted_at' => now()->subDays(2),
            'invitation_expires_at' => now()->addDays(5),
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
        $response = $this->actingAs($user)
            ->getJson("/api/v1/invited-users/{$acceptedUser->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invitation_status',
                    'invitation_expires_at',
                    'invitation_accepted_at',
                    'is_expired',
                    'financers',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('accepted', $data['invitation_status']);
        $this->assertNotNull($data['invitation_accepted_at']);
        $this->assertNotNull($data['invitation_expires_at']);
        $this->assertFalse($data['is_expired']);

        // Verify financers are returned
        $this->assertIsArray($data['financers']);
        $this->assertCount(1, $data['financers']);
        $this->assertEquals('active', $data['financers'][0]['status']);
    }

    #[Test]
    public function it_shows_revoked_invitation_with_status_fields(): void
    {
        // Arrange
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $financer = ModelFactory::createFinancer();

        $revokedUser = ModelFactory::createUser([
            'email' => 'revoked@test.com',
            'invitation_status' => 'revoked',
            'invitation_expires_at' => now()->addDays(5),
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
        $response = $this->actingAs($user)
            ->getJson("/api/v1/invited-users/{$revokedUser->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invitation_status',
                    'invitation_expires_at',
                    'is_expired',
                    'financers',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('revoked', $data['invitation_status']);
        $this->assertNotNull($data['invitation_expires_at']);
        $this->assertFalse($data['is_expired']);

        // Verify financers are returned
        $this->assertIsArray($data['financers']);
        $this->assertCount(1, $data['financers']);
        $this->assertEquals('inactive', $data['financers'][0]['status']);
    }

    #[Test]
    public function it_shows_expired_invitation_with_status_fields(): void
    {
        // Arrange
        $user = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
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
        $response = $this->actingAs($user)
            ->getJson("/api/v1/invited-users/{$expiredUser->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'invitation_status',
                    'invitation_expires_at',
                    'is_expired',
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals('pending', $data['invitation_status']);
        $this->assertNotNull($data['invitation_expires_at']);
        $this->assertTrue($data['is_expired']);
    }
}
