<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Enums\IDP\PermissionDefaults;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Policies\SitePolicy;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('site')]
#[Group('policy')]
class SitePolicyTest extends TestCase
{
    use DatabaseTransactions;

    private SitePolicy $policy;

    private User $user;

    private User $otherUser;

    private Site $site;

    private Site $otherSite;

    private Financer $financer;

    private Financer $otherFinancer;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new SitePolicy;

        // Create team for permissions
        $this->team = ModelFactory::createTeam();
        setPermissionsTeamId($this->team->id);

        // Create permissions
        Permission::firstOrCreate([
            'name' => PermissionDefaults::READ_SITE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::CREATE_SITE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::UPDATE_SITE,
            'guard_name' => 'api',
        ]);
        Permission::firstOrCreate([
            'name' => PermissionDefaults::DELETE_SITE,
            'guard_name' => 'api',
        ]);

        // Create users
        $this->user = User::factory()->create(['team_id' => $this->team->id]);
        $this->otherUser = User::factory()->create(['team_id' => $this->team->id]);

        // Create financers
        $this->financer = Financer::factory()->create();
        $this->otherFinancer = Financer::factory()->create();

        // Create sites
        $this->site = Site::factory()->create([
            'financer_id' => $this->financer->id,
            'name' => ['en-GB' => 'Test Site', 'fr-FR' => 'Site Test'],
        ]);

        $this->otherSite = Site::factory()->create([
            'financer_id' => $this->otherFinancer->id,
            'name' => ['en-GB' => 'Other Site', 'fr-FR' => 'Autre Site'],
        ]);

        // Clean context
        Context::flush();
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }

    #[Test]
    public function user_without_read_site_permission_cannot_view_any_sites(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->viewAny($this->user));
        $this->assertFalse($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_with_read_site_permission_can_view_any_sites(): void
    {
        // Arrange
        setPermissionsTeamId($this->team->id);
        $this->user->givePermissionTo(PermissionDefaults::READ_SITE);
        $this->otherUser->givePermissionTo(PermissionDefaults::READ_SITE);

        // Act & Assert
        $this->assertTrue($this->policy->viewAny($this->user));
        $this->assertTrue($this->policy->viewAny($this->otherUser));
    }

    #[Test]
    public function user_without_read_site_permission_cannot_view_sites(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->view($this->user, $this->site));
        $this->assertFalse($this->policy->view($this->otherUser, $this->otherSite));
    }

    #[Test]
    public function user_with_read_site_permission_can_view_site_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->view($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_read_site_permission_cannot_view_site_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->otherSite));
    }

    #[Test]
    public function user_without_create_site_permission_cannot_create_sites(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->create($this->otherUser));
    }

    #[Test]
    public function user_with_create_site_permission_can_create_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::CREATE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->create($userWithPermission));
    }

    #[Test]
    public function user_without_update_site_permission_cannot_update_sites(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->update($this->user, $this->site));
        $this->assertFalse($this->policy->update($this->otherUser, $this->otherSite));
    }

    #[Test]
    public function user_with_update_site_permission_can_update_site_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->update($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_update_site_permission_cannot_update_site_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->otherSite));
    }

    #[Test]
    public function user_without_delete_site_permission_cannot_delete_sites(): void
    {
        // Act & Assert
        $this->assertFalse($this->policy->delete($this->user, $this->site));
        $this->assertFalse($this->policy->delete($this->otherUser, $this->otherSite));
    }

    #[Test]
    public function user_with_delete_site_permission_can_delete_site_from_same_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertTrue($this->policy->delete($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_delete_site_permission_cannot_delete_site_from_different_financer(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for currentFinancerId
        Context::add('financer_id', $this->financer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->otherSite));
    }

    #[Test]
    public function user_without_permissions_cannot_access_sites(): void
    {
        // Act & Assert - cannot access any site operations
        $this->assertFalse($this->policy->view($this->user, $this->site));
        $this->assertFalse($this->policy->create($this->user));
        $this->assertFalse($this->policy->update($this->user, $this->site));
        $this->assertFalse($this->policy->delete($this->user, $this->site));
    }

    #[Test]
    public function user_with_read_site_permission_but_no_current_financer_cannot_view_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_update_site_permission_but_no_current_financer_cannot_update_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_delete_site_permission_but_no_current_financer_cannot_delete_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // No context set for currentFinancerId

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_read_site_permission_and_wrong_current_financer_cannot_view_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::READ_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->view($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_update_site_permission_and_wrong_current_financer_cannot_update_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::UPDATE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->update($userWithPermission, $this->site));
    }

    #[Test]
    public function user_with_delete_site_permission_and_wrong_current_financer_cannot_delete_sites(): void
    {
        // Arrange
        $userWithPermission = User::factory()->create(['team_id' => $this->team->id]);
        setPermissionsTeamId($this->team->id);
        $userWithPermission->givePermissionTo(PermissionDefaults::DELETE_SITE);
        $userWithPermission->financers()->attach($this->financer->id, ['active' => true]);

        // Set context for different financer
        Context::add('financer_id', $this->otherFinancer->id);

        // Act & Assert
        $this->assertFalse($this->policy->delete($userWithPermission, $this->site));
    }
}
