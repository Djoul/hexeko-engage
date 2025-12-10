<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\PermissionDefaults;
use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\Permission;
use App\Models\Segment;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use Spatie\Permission\PermissionRegistrar;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('segment')]
class SegmentControllerTest extends ProtectedRouteTestCase
{
    protected string $route = 'segments.index';

    protected string $permission = PermissionDefaults::READ_SEGMENT;

    private Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ensureSegmentPermissions();

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_SUPER_ADMIN);
        $this->financer = $this->auth->financers->first();

        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id  // Set current financer for global scopes
        );
    }

    protected function tearDown(): void
    {
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_lists_segments_for_the_authenticated_financer(): void
    {
        // Arrange
        $segmentAlpha = Segment::factory()->create([
            'name' => 'Segment Alpha',
            'description' => 'Alpha description',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);
        $segmentBeta = Segment::factory()->create([
            'name' => 'Segment Beta',
            'description' => 'Beta description',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);
        $externalSegment = Segment::factory()->create([
            'name' => 'External Segment',
            'financer_id' => Financer::factory()->create()->id,
            'filters' => [],
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('segments.index', [
            'financer_id' => $this->financer->id,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment([
                'id' => $segmentAlpha->id,
                'name' => 'Segment Alpha',
            ])
            ->assertJsonFragment([
                'id' => $segmentBeta->id,
                'name' => 'Segment Beta',
            ])
            ->assertJsonMissing(['id' => $externalSegment->id]);
    }

    #[Test]
    public function it_displays_a_segment_with_user_counts(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Segment Details',
            'description' => 'Segment details description',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);
        $user = ModelFactory::createUser([
            'financers' => [
                ['financer' => $this->financer],
            ],
        ]);
        $segment->users()->attach($user->id);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('segments.show', [
            'segment' => $segment->id,
            'financer_id' => $this->financer->id,
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonFragment([
                'id' => $segment->id,
                'name' => 'Segment Details',
                'financer_id' => $this->financer->id,
            ])
            ->assertJsonPath('data.users_count', 1);
    }

    #[Test]
    public function it_returns_not_found_when_segment_is_missing(): void
    {
        // Arrange
        $missingId = Uuid::uuid7()->toString();

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('segments.show', [
            'segment' => $missingId,
            'financer_id' => $this->financer->id,
        ]));

        // Assert
        $response->assertNotFound();
    }

    #[Test]
    public function it_validates_required_fields_when_creating_segments(): void
    {
        // Arrange

        // Act
        $response = $this->actingAs($this->auth)->postJson(route('segments.store', [
            'financer_id' => $this->financer->id,
        ]), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_creates_a_segment(): void
    {
        // Arrange
        $payload = [
            'financer_id' => $this->financer->id,
            'name' => 'Customer Loyalty',
            'description' => 'Customers with high engagement scores',
            'filters' => [],
        ];

        // Act
        $response = $this->actingAs($this->auth)->postJson(route('segments.store', [
            'financer_id' => $this->financer->id,
        ]), $payload);

        // Assert
        $response->assertCreated()
            ->assertJsonFragment([
                'name' => 'Customer Loyalty',
                'financer_id' => $this->financer->id,
            ]);

        $createdSegmentId = $response->json('data.id');
        $this->assertNotNull($createdSegmentId);
        $this->assertDatabaseHas('segments', [
            'id' => $createdSegmentId,
            'financer_id' => $this->financer->id,
            'name' => 'Customer Loyalty',
        ]);
        $this->assertSame($payload['filters'], Segment::findOrFail($createdSegmentId)->filters);
    }

    #[Test]
    public function it_updates_a_segment(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Initial Segment',
            'description' => 'Initial description',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        $payload = [
            'financer_id' => $this->financer->id,
            'name' => 'Updated Segment',
            'description' => 'Updated description',
            'filters' => [],
        ];

        // Act
        $response = $this->actingAs($this->auth)->putJson(route('segments.update', [
            'segment' => $segment->id,
            'financer_id' => $this->financer->id,
        ]), $payload);

        // Assert
        $response->assertOk()
            ->assertJsonFragment([
                'id' => $segment->id,
                'name' => 'Updated Segment',
                'description' => 'Updated description',
            ]);

        $segment->refresh();
        $this->assertSame('Updated Segment', $segment->name);
        $this->assertSame('Updated description', $segment->description);
        $this->assertSame($payload['filters'], $segment->filters);
    }

    #[Test]
    public function it_deletes_a_segment(): void
    {
        // Arrange
        $segment = Segment::factory()->create([
            'name' => 'Segment to delete',
            'financer_id' => $this->financer->id,
            'filters' => [],
        ]);

        // Act
        $response = $this->actingAs($this->auth)->deleteJson(route('segments.destroy', [
            'segment' => $segment->id,
            'financer_id' => $this->financer->id,
        ]));

        // Assert
        $response->assertNoContent();
        $this->assertSoftDeleted('segments', ['id' => $segment->id]);
    }

    #[Test]
    public function it_returns_not_found_when_deleting_missing_segment(): void
    {
        // Arrange
        $missingId = Uuid::uuid7()->toString();

        // Act
        $response = $this->actingAs($this->auth)->deleteJson(route('segments.destroy', [
            'segment' => $missingId,
            'financer_id' => $this->financer->id,
        ]));

        // Assert
        $response->assertNotFound();
    }

    /**
     * Ensure CRUD permissions exist for segments.
     */
    private function ensureSegmentPermissions(): void
    {
        foreach ([PermissionDefaults::CREATE_SEGMENT, PermissionDefaults::READ_SEGMENT, PermissionDefaults::UPDATE_SEGMENT, PermissionDefaults::DELETE_SEGMENT] as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'api']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
