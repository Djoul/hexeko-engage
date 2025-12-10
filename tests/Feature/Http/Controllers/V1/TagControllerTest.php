<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Security\AuthorizationMode;
use App\Models\Financer;
use App\Models\Tag;
use Illuminate\Support\Facades\Context;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Traits\TestsFinancerSecurity;
use Tests\ProtectedRouteTestCase;

#[Group('tag')]
class TagControllerTest extends ProtectedRouteTestCase
{
    use TestsFinancerSecurity;

    protected Financer $financer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN, returnDetails: true);
        $this->financer = $this->auth->financers->first();

        // Hydrate authorization context with explicit financer access
        authorizationContext()->hydrate(
            AuthorizationMode::SELF,
            [$this->financer->id],
            [$this->financer->division_id],
            [],
            $this->financer->id
        );

        // Set Context for global scopes
        Context::add('financer_id', $this->financer->id);
        Context::add('accessible_financers', [$this->financer->id]);
        Context::add('accessible_divisions', [$this->financer->division_id]);
    }

    #[Test]
    public function it_can_list_tags(): void
    {
        // Arrange
        Tag::factory()->count(3)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('tags.index', ['financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'financer_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    #[Test]
    public function it_filters_tags_by_name(): void
    {
        // Arrange
        Tag::factory()->create([
            'name' => 'Marketing',
            'financer_id' => $this->financer->id,
        ]);
        Tag::factory()->create([
            'name' => 'Sales',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('tags.index', [
                'financer_id' => $this->financer->id,
                'name' => 'Marketing',
            ]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_tags_by_financer_id(): void
    {
        // Arrange
        $otherFinancer = Financer::factory()->create();

        Context::add('accessible_financers', [$this->financer->id, $otherFinancer->id]);

        Tag::factory()->create(['financer_id' => $this->financer->id]);
        Tag::factory()->create(['financer_id' => $otherFinancer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('tags.index', ['financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_tags_by_date_from(): void
    {
        // Arrange
        Tag::factory()->create([
            'created_at' => '2023-01-01',
            'updated_at' => '2023-01-01',
            'financer_id' => $this->financer->id,
        ]);
        Tag::factory()->create([
            'created_at' => '2024-01-02',
            'updated_at' => '2024-01-02',
            'financer_id' => $this->financer->id,
        ]);
        Tag::factory()->create([
            'created_at' => '2024-01-03',
            'updated_at' => '2024-01-03',
            'financer_id' => $this->financer->id,
        ]);
        Tag::factory()->create([
            'created_at' => '2024-01-04',
            'updated_at' => '2024-01-04',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-02',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-01-03',
            'date_from_fields' => 'created_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Arrange
        Tag::factory()->create([
            'created_at' => '2023-12-01',
            'updated_at' => '2024-03-01',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_from' => '2024-03-01',
            'date_from_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(1, 'data');
    }

    #[Test]
    public function it_filters_tags_by_date_to(): void
    {
        // Arrange
        Tag::factory()->create(['updated_at' => '2023-01-31', 'financer_id' => $this->financer->id]);
        Tag::factory()->create(['updated_at' => '2023-02-28', 'financer_id' => $this->financer->id]);
        Tag::factory()->create(['updated_at' => '2023-02-01', 'financer_id' => $this->financer->id]);
        Tag::factory()->create(['updated_at' => '2023-01-01', 'financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-01-31',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(2, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-28',
            'date_to_fields' => 'updated_at',
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(4, 'data');

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'date_to' => '2023-02-01',
            'date_to_fields' => ['created_at', 'updated_at'],
        ]));

        // Assert
        $response->assertOk()->assertJsonCount(3, 'data');
    }

    #[Test]
    public function it_returns_no_tags_if_filters_do_not_match(): void
    {
        // Arrange
        // No tags created

        // Act
        $response = $this->actingAs($this->auth)->getJson(route('tags.index', [
            'financer_id' => $this->financer->id,
            'name' => 'Nonexistent Tag',
        ]));

        // Assert
        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    #[Test]
    public function it_store_validates_input(): void
    {
        // Arrange
        // Empty payload

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson(route('tags.store', ['financer_id' => $this->financer->id]), []);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    #[Test]
    public function it_persists_and_redirects_when_storing(): void
    {
        // Arrange
        $payload = [
            'name' => 'Marketing',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson(route('tags.store', ['financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertStatus(201);

        $createdTagId = $response->json('data.id');
        $this->assertNotNull($createdTagId);

        $this->assertDatabaseHas('tags', [
            'id' => $createdTagId,
            'financer_id' => $this->financer->id,
        ]);

        $createdTag = Tag::find($createdTagId);

        $this->assertEquals('Marketing', $createdTag->name);
    }

    #[Test]
    public function it_displays_a_tag(): void
    {
        // Arrange
        $tag = Tag::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('tags.show', ['tag' => $tag, 'financer_id' => $this->financer->id]));

        // Assert
        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'financer_id',
                ],
            ]);
    }

    #[Test]
    public function it_validates_input_when_updating(): void
    {
        // Arrange
        $tag = Tag::factory()->create([
            'name' => 'Old Name',
            'financer_id' => $this->financer->id,
        ]);

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('tags.update', ['tag' => $tag, 'financer_id' => $this->financer->id]), ['name' => '']);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $this->assertDatabaseHas('tags', ['id' => $tag->id]);

        $tag->refresh();
        $this->assertEquals('Old Name', $tag->name);
    }

    #[Test]
    public function it_persists_changes_and_redirects_when_updating(): void
    {
        // Arrange
        $tag = Tag::factory()->create([
            'name' => 'Old Tag',
            'financer_id' => $this->financer->id,
        ]);

        $payload = [
            'name' => 'New Tag',
            'financer_id' => $this->financer->id,
        ];

        // Act
        $response = $this->actingAs($this->auth)
            ->putJson(route('tags.update', ['tag' => $tag, 'financer_id' => $this->financer->id]), $payload);

        // Assert
        $response->assertOk();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);

        $updatedTag = $tag->fresh();

        $this->assertEquals('New Tag', $updatedTag->name);
    }

    #[Test]
    public function it_deletes_and_redirects(): void
    {
        // Arrange
        $tag = Tag::factory()->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->deleteJson(route('tags.destroy', ['tag' => $tag, 'financer_id' => $this->financer->id]));

        // Assert
        $response->assertStatus(204);
        $this->assertSoftDeleted('tags', ['id' => $tag->id]);
    }

    #[Test]
    public function it_can_paginate_tags(): void
    {
        // Arrange
        Tag::factory()->count(25)->create(['financer_id' => $this->financer->id]);

        // Act
        $response = $this->actingAs($this->auth)
            ->getJson(route('tags.index', [
                'financer_id' => $this->financer->id,
                'per_page' => 10,
            ]));

        // Assert
        $response->assertStatus(200)
            ->assertJsonCount(10, 'data')
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);
    }

    #[Test]
    public function it_enforces_financer_security(): void
    {
        $this->assertCannotAccessOtherFinancerResource(Tag::class, 'tags', 'tag');
    }
}
