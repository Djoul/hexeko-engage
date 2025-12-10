<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Enums\IDP\RoleDefaults;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Role;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['users', 'roles', 'model_has_roles', 'permissions', 'model_has_permissions'], scope: 'class')]
#[Group('user')]
class CreateInvitedUserWithRoleTest extends ProtectedRouteTestCase
{
    const URI = '/api/v1/invited-users';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Event::fake();

        // Create necessary roles for testing
        $this->createRoles();
    }

    private function createRoles(): void
    {
        $roles = [
            RoleDefaults::HEXEKO_SUPER_ADMIN,
            RoleDefaults::HEXEKO_ADMIN,
            RoleDefaults::DIVISION_SUPER_ADMIN,
            RoleDefaults::DIVISION_ADMIN,
            RoleDefaults::FINANCER_SUPER_ADMIN,
            RoleDefaults::FINANCER_ADMIN,
            RoleDefaults::BENEFICIARY,
        ];

        foreach ($roles as $role) {
            if (! Role::where('name', $role)->exists()) {
                ModelFactory::createRole(['name' => $role]);
            }
        }
    }

    #[Test]
    public function it_creates_invited_user_with_intended_role(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with DIVISION_ADMIN role (can manage financer roles)
        $this->user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::DIVISION_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'financer_id' => $financer->id,
            'intended_role' => RoleDefaults::BENEFICIARY,
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'invitation_status',
                    'invitation_metadata',
                ],
                'message',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'invitation_status' => 'pending',
        ]);

        // Check that invitation_metadata contains the intended role
        $invitedUser = User::where('email', 'john.doe@example.com')
            ->where('invitation_status', 'pending')
            ->first();
        $this->assertEquals(RoleDefaults::BENEFICIARY, $invitedUser->invitation_metadata['intended_role']);
        $this->assertEquals($this->user->id, $invitedUser->invited_by);
        $this->assertNotNull($invitedUser->invitation_token);
        $this->assertNotNull($invitedUser->invitation_expires_at);
    }

    #[Test]
    public function it_prevents_unauthorized_role_assignment(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with FINANCER_ADMIN role
        $this->user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_ADMIN, // Single role system
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::FINANCER_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane.smith@example.com',
            'financer_id' => $financer->id,
            'intended_role' => RoleDefaults::DIVISION_ADMIN, // Cannot assign this role
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertUnprocessable()
            ->assertJson([
                'message' => 'You are not authorized to assign the role: division_admin',
            ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'jane.smith@example.com',
            'invitation_status' => 'pending',
        ]);
    }

    #[Test]
    public function it_allows_higher_roles_to_invite_admin_users(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with DIVISION_ADMIN role
        $this->user = ModelFactory::createUser([
            'email' => 'division_admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::DIVISION_ADMIN, // Single role system
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::DIVISION_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'admin.user@example.com',
            'financer_id' => $financer->id,
            'intended_role' => RoleDefaults::FINANCER_ADMIN,
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertCreated();

        $invitedUser = User::where('email', 'admin.user@example.com')
            ->where('invitation_status', 'pending')
            ->first();
        $this->assertEquals(RoleDefaults::FINANCER_ADMIN, $invitedUser->invitation_metadata['intended_role']);
    }

    #[Test]
    public function it_creates_beneficiary_by_default_when_no_role_specified(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with FINANCER_ADMIN role
        $this->user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_ADMIN, // Single role system
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::FINANCER_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'Default',
            'last_name' => 'User',
            'email' => 'default.user@example.com',
            'financer_id' => $financer->id,
            // No intended_role specified
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertCreated();

        $invitedUser = User::where('email', 'default.user@example.com')
            ->where('invitation_status', 'pending')
            ->first();
        $this->assertEquals(RoleDefaults::BENEFICIARY, $invitedUser->invitation_metadata['intended_role']);
    }

    #[Test]
    public function it_validates_role_value(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with FINANCER_ADMIN role
        $this->user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                    'role' => RoleDefaults::FINANCER_ADMIN, // Single role system
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::FINANCER_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'Invalid',
            'last_name' => 'Role',
            'email' => 'invalid.role@example.com',
            'financer_id' => $financer->id,
            'intended_role' => 'invalid_role',
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['intended_role']);
    }

    #[Test]
    public function it_includes_optional_fields_when_provided(): void
    {
        // Create financer
        $financer = ModelFactory::createFinancer();

        // Create test user with DIVISION_ADMIN role (can manage financer roles)
        $this->user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => true,
                ],
            ],
        ]);

        $this->user->assignRole(RoleDefaults::DIVISION_ADMIN);

        $this->actingAs($this->user);

        $data = [
            'first_name' => 'Complete',
            'last_name' => 'User',
            'email' => 'complete.user@example.com',
            'financer_id' => $financer->id,
            'intended_role' => RoleDefaults::BENEFICIARY,
            'phone' => '+33612345678',
            'external_id' => 'EMP-12345',
            'sirh_id' => 'SIRH-98765',
        ];

        $response = $this->postJson(self::URI, $data);

        $response->assertCreated();

        $this->assertDatabaseHas('users', [
            'email' => 'complete.user@example.com',
            'invitation_status' => 'pending',
            'phone' => '+33612345678',
        ]);

        // Verify user-financer pivot data
        $invitedUser = User::where('email', 'complete.user@example.com')
            ->where('invitation_status', 'pending')
            ->first();

        $this->assertNotNull($invitedUser);

        // external_id is stored in invitation_metadata (no longer a column in users table)
        $this->assertEquals('EMP-12345', $invitedUser->invitation_metadata['external_id']);

        // sirh_id is stored in financer_user pivot table
        $pivot = $invitedUser->financers()->where('financer_id', $financer->id)->first();
        $this->assertNotNull($pivot);
        $this->assertEquals('SIRH-98765', $pivot->pivot->sirh_id);
    }
}
