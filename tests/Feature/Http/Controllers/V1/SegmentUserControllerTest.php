<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Permission;
use App\Models\Segment;
use App\Models\User;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('segment')]
class SegmentUserControllerTest extends ProtectedRouteTestCase
{
    protected string $route = 'segments.users.index';

    protected string $permission = PermissionDefaults::READ_SEGMENT;

    private Segment $segment;

    private string $financerId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->ensureSegmentPermissions();

        setPermissionsTeamId($this->auth->team_id);
        $this->auth->givePermissionTo(PermissionDefaults::READ_SEGMENT);
        $financer = $this->auth->financers->first();
        $this->financerId = $this->auth->financers->first()->id;

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$financer->id],
            [$financer->division_id],
            [],
            $financer->id  // Set current financer for global scopes
        );

        $this->segment = Segment::factory()->create([
            'financer_id' => $this->financerId,
            'filters' => [],
        ]);
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_lists_users_attached_to_segment(): void
    {
        // Arrange
        $attachedUser = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->segment->users()->attach($attachedUser->id);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('segments.users.index', [
            'segment' => $this->segment->id,
            'financer_id' => $this->financerId,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment([
                'id' => $attachedUser->id,
                'email' => $attachedUser->email,
            ])
            ->assertJsonMissing(['id' => $otherUser->id]);
    }

    #[Test]
    public function it_lists_computed_users_for_segment(): void
    {
        // Arrange
        $computedUser = User::factory()->create();
        $this->auth->financers->first()->users()->attach($computedUser->id, [
            'active' => true,
            'role' => 'beneficiary',
            'from' => now(),
        ]);

        $segmentWithoutAttachments = Segment::factory()->create([
            'financer_id' => $this->financerId,
            'filters' => [],
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('segments.users.computed', [
            'segment' => $segmentWithoutAttachments->id,
            'financer_id' => $this->financerId,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonFragment([
                'id' => $computedUser->id,
                'email' => $computedUser->email,
            ]);
    }

    #[Test]
    public function it_returns_forbidden_when_user_cannot_view_segment(): void
    {
        // Arrange
        $unauthorizedUser = ModelFactory::createUser();

        // Act
        $response = $this->actingAs($unauthorizedUser)->getJson(route('segments.users.index', [
            'segment' => $this->segment->id,
            'financer_id' => $this->financerId,
        ]));

        // Assert
        $response->assertForbidden();
    }

    private function ensureSegmentPermissions(): void
    {
        Permission::firstOrCreate(['name' => PermissionDefaults::READ_SEGMENT, 'guard_name' => 'api']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
