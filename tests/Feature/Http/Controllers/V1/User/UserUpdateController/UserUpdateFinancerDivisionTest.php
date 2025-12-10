<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\V1\User\UserUpdateController;

use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\ProtectedRouteTestCase;

#[FlushTables(tables: ['financers', 'financer_user'], scope: 'class')]
#[Group('user')]
#[Group('financer')]
class UserUpdateFinancerDivisionTest extends ProtectedRouteTestCase
{
    private Division $division1;

    private Division $division2;

    private Financer $financerDiv1;

    private Financer $financerDiv2;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Create divisions
        $this->division1 = Division::factory()->create(['name' => 'Division 1']);
        $this->division2 = Division::factory()->create(['name' => 'Division 2']);

        // Create financers in different divisions
        $this->financerDiv1 = Financer::factory()->create([
            'name' => 'Financer Division 1',
            'division_id' => $this->division1->id,
        ]);

        $this->financerDiv2 = Financer::factory()->create([
            'name' => 'Financer Division 2',
            'division_id' => $this->division2->id,
        ]);

        // Create test user attached to financer in division 1
        $this->testUser = User::factory()->create();
        $this->testUser->financers()->attach($this->financerDiv1->id, [
            'active' => true,
            'sirh_id' => 'TEST-001',
            'from' => now()->subYear(),
            'to' => null,
            'role' => 'beneficiary',
        ]);
    }

    #[Test]
    public function division_admin_cannot_add_financer_from_different_division_via_api(): void
    {
        // Arrange - Create division admin user belonging to division 1
        $adminUser = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);
        $adminUser->financers()->attach($this->financerDiv1->id, ['active' => true]);

        // Prepare update data
        $updateData = [
            'financers' => [
                ['id' => $this->financerDiv1->id], // Keep existing
                ['id' => $this->financerDiv2->id], // Try to add from different division
            ],
        ];

        // Act
        $response = $this->actingAs($adminUser)
            ->putJson("/api/v1/users/{$this->testUser->id}", $updateData);

        // Assert - Should fail with 500 due to InvalidArgumentException
        $response->assertStatus(500);
    }

    #[Test]
    public function division_admin_can_add_financer_from_same_division_via_api(): void
    {
        // Arrange - Create division admin user belonging to division 1
        $adminUser = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);
        $adminUser->financers()->attach($this->financerDiv1->id, ['active' => true]);

        // Create another financer in same division
        $anotherFinancerDiv1 = Financer::factory()->create([
            'name' => 'Another Financer Division 1',
            'division_id' => $this->division1->id,
        ]);

        // Admin must also have access to anotherFinancerDiv1 to see it in response
        $adminUser->financers()->attach($anotherFinancerDiv1->id, ['active' => true]);
        $adminUser->refresh();

        // Prepare update data
        $updateData = [
            'financers' => [
                ['id' => $this->financerDiv1->id], // Keep existing
                ['id' => $anotherFinancerDiv1->id], // Add from same division
            ],
        ];

        // Act
        $response = $this->actingAs($adminUser)
            ->putJson("/api/v1/users/{$this->testUser->id}", $updateData);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.financers');
    }

    #[Test]
    public function god_user_can_add_financer_from_any_division_via_api(): void
    {
        // Arrange - Create GOD user
        $godUser = $this->createAuthUser(RoleDefaults::GOD);

        // Prepare update data
        $updateData = [
            'financers' => [
                ['id' => $this->financerDiv1->id],
                ['id' => $this->financerDiv2->id], // From different division
            ],
        ];

        // Act
        $response = $this->actingAs($godUser)
            ->putJson("/api/v1/users/{$this->testUser->id}", $updateData);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(2, 'data.financers');
    }

    #[Test]
    public function user_cannot_remove_all_financers_via_api(): void
    {
        // Arrange - Create division admin user belonging to division 1
        $adminUser = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);
        $adminUser->financers()->attach($this->financerDiv1->id, ['active' => true]);

        // Prepare update data - try to remove all financers
        $updateData = [
            'financers' => [],
        ];

        // Act
        $response = $this->actingAs($adminUser)
            ->putJson("/api/v1/users/{$this->testUser->id}", $updateData);

        // Assert - Should fail
        $response->assertStatus(500);
    }

    #[Test]
    public function update_without_financers_field_maintains_existing_financers(): void
    {
        // Arrange - Create division admin user belonging to division 1
        $adminUser = $this->createAuthUser(RoleDefaults::DIVISION_ADMIN);
        $adminUser->financers()->attach($this->financerDiv1->id, ['active' => true]);

        // Refresh to ensure financers are loaded for security filtering
        $adminUser->refresh();

        // Prepare update data without financers field
        $updateData = [
            'first_name' => 'Updated Name',
        ];

        // Act
        $response = $this->actingAs($adminUser)
            ->putJson("/api/v1/users/{$this->testUser->id}", $updateData);

        // Assert
        $response->assertStatus(200);
        $response->assertJsonCount(1, 'data.financers');
        $response->assertJsonPath('data.financers.0.id', $this->financerDiv1->id);
        $response->assertJsonPath('data.first_name', 'Updated Name');
    }
}
