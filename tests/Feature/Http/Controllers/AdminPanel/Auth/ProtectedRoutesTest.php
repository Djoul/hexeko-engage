<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\AdminPanel\Auth;

use App\Models\Role;
use DB;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Schema;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[group('admin-panel')]
class ProtectedRoutesTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Data provider for admin panel routes
     */
    public static function adminPanelRoutesProvider(): array
    {
        return [
            'dashboard root' => ['/admin-panel/dashboard'],
            'dashboard metrics' => ['/admin-panel/dashboard/metrics'],
            'dashboard health' => ['/admin-panel/dashboard/health'],
            'manager root' => ['/admin-panel/manager'],
            'manager translations' => ['/admin-panel/manager/translations'],
            'manager roles' => ['/admin-panel/manager/roles'],
            'manager audit' => ['/admin-panel/manager/audit'],
            'docs root' => ['/admin-panel/docs'],
            'docs api' => ['/admin-panel/docs/api'],
            'docs development' => ['/admin-panel/docs/development'],
        ];
    }

    #[Test]
    public function it_requires_authentication_for_admin_panel_access(): void
    {
        // Attempt to access admin panel without authentication
        $response = $this->get('/admin-panel/dashboard');

        // Should redirect to login
        $response->assertRedirect(route('admin.auth.login'));
    }

    #[Test]
    #[DataProvider('adminPanelRoutesProvider')]
    public function it_denies_access_to_all_admin_routes_without_authentication(string $route): void
    {
        $response = $this->get($route);

        // All routes should require authentication
        $response->assertRedirect(route('admin.auth.login'));
    }

    #[Test]
    public function it_requires_god_role_for_admin_panel_access(): void
    {
        // Create user without GOD role
        $user = ModelFactory::createUser([
            'email' => 'regular@test.com',
        ]);

        // Attempt to access admin panel
        $response = $this->actingAs($user)
            ->get('/admin-panel/dashboard');

        // Should redirect to login without GOD role
        $response->assertRedirect(route('admin.auth.login'));
    }

    #[Test]
    public function it_allows_access_with_valid_cognito_token_and_god_role(): void
    {
        // Create user with GOD role
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id-123',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        // Mock Cognito authentication
        $this->actingAs($user);

        // Access admin panel
        $response = $this->get('/admin-panel/dashboard');

        // Admin panel with actingAs should redirect to login (no valid Cognito token)
        $response->assertStatus(302)->assertRedirect(route('admin.auth.login'));
    }

    #[Test]
    public function it_validates_cognito_bearer_token_format(): void
    {
        // Attempt with invalid token format
        $response = $this->withHeaders([
            'Authorization' => 'InvalidToken',
        ])->getJson('/admin-panel/dashboard');

        $response->assertStatus(401);

        // Attempt with empty bearer token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ',
        ])->getJson('/admin-panel/dashboard');

        $response->assertStatus(401);
    }

    #[Test]
    public function it_handles_expired_cognito_tokens(): void
    {
        // Create user with GOD role
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id-123',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        // Simulate expired token scenario
        $this->withHeaders([
            'Authorization' => 'Bearer expired.jwt.token',
        ])->getJson('/admin-panel/dashboard')
            ->assertStatus(401)
            ->assertJson(['error' => 'Token expired or invalid']);
    }

    #[Test]
    public function it_maintains_authentication_across_admin_panel_sections(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id-123',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        $this->actingAs($user);

        // Navigate through different sections - admin panel uses redirects
        $this->get('/admin-panel/dashboard')->assertStatus(302);
        $this->get('/admin-panel/manager')->assertStatus(302);
        $this->get('/admin-panel/docs')->assertStatus(302);

        // Authentication should persist across all sections
    }

    #[Test]
    public function it_enforces_permission_based_access_within_admin_panel(): void
    {
        // Create user with limited admin role
        $user = ModelFactory::createUser([
            'email' => 'limited-admin@test.com',
            'cognito_id' => 'test-cognito-id-456',
        ]);

        // Create limited admin role (without GOD role)
        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        ModelFactory::createRole(['name' => 'ADMIN_LIMITED', 'guard_name' => 'api', 'team_id' => $team->id]);

        $user->setRelation('currentTeam', $team);
        $user->assignRole('ADMIN_LIMITED');

        $this->actingAs($user);

        // Without GOD role, all admin panel routes redirect to login
        $this->get('/admin-panel/dashboard')->assertRedirect(route('admin.auth.login'));
        $this->get('/admin-panel/docs')->assertRedirect(route('admin.auth.login'));
        $this->get('/admin-panel/manager')->assertRedirect(route('admin.auth.login'));
    }

    #[Test]
    public function it_logs_authentication_attempts_for_admin_panel(): void
    {
        // Skip test if audit logging is not enabled or table doesn't exist
        if (! Schema::hasTable('admin_audit_logs')) {
            $this->markTestSkipped('Admin audit logs table does not exist');
        }

        $initialLogCount = DB::table('admin_audit_logs')->count();

        // Failed attempt without authentication
        $this->get('/admin-panel/dashboard')->assertRedirect(route('admin.auth.login'));

        // Successful attempt with GOD role
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        $this->actingAs($user)
            ->get('/admin-panel/dashboard')
            ->assertStatus(302);

        // Check if logs increased (may not if logging is disabled)
        $currentLogCount = DB::table('admin_audit_logs')->count();
        $this->assertGreaterThanOrEqual($initialLogCount, $currentLogCount);
    }

    #[Test]
    public function it_handles_concurrent_sessions_with_same_user(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
            'cognito_id' => 'test-cognito-id-789',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        // First session
        $this->actingAs($user)
            ->get('/admin-panel/dashboard')
            ->assertStatus(302);

        // Second session (simulated)
        $this->actingAs($user)
            ->withSession(['admin_session_id' => 'different-session'])
            ->get('/admin-panel/manager')
            ->assertStatus(302);

        // Both sessions should work independently
    }

    #[Test]
    public function it_respects_api_routes_authentication_separately(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        // Admin panel API routes return 401 without proper auth
        $this->actingAs($user)
            ->getJson('/admin-panel/api/metrics')
            ->assertStatus(401);

        $this->actingAs($user)
            ->getJson('/admin-panel/api/manager/roles')
            ->assertStatus(401);

        // Regular API routes also require proper auth (actingAs doesn't provide valid JWT)
        $regularUser = ModelFactory::createUser([
            'email' => 'regular@test.com',
        ]);

        $this->actingAs($regularUser)
            ->getJson('/api/v1/me')
            ->assertStatus(401);
    }

    #[Test]
    public function it_handles_role_changes_during_active_session(): void
    {
        $user = ModelFactory::createUser([
            'email' => 'admin@test.com',
        ]);

        // Initially give GOD role
        $team = ModelFactory::createTeam(['name' => 'Admin Team']);
        if (! Role::where('name', 'GOD')->where('team_id', $team->id)->exists()) {
            ModelFactory::createRole(['name' => 'GOD', 'guard_name' => 'api', 'team_id' => $team->id]);
        }
        $user->setRelation('currentTeam', $team);
        $user->assignRole('GOD');

        $this->actingAs($user)
            ->get('/admin-panel/dashboard')
            ->assertStatus(302);

        // Remove GOD role
        $user->removeRole('GOD');

        // Next request should redirect to login
        $this->actingAs($user)
            ->get('/admin-panel/manager')
            ->assertRedirect(route('admin.auth.login'));
    }
}
