<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\CRUD\UpdateUserAction;
use App\Enums\IDP\RoleDefaults;
use App\Models\Division;
use App\Models\Financer;
use App\Models\User;
use App\Services\Models\UserProfileImageService;
use App\Services\Models\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('financer')]
#[Group('division-control')]
class UpdateUserFinancerDivisionTest extends ProtectedRouteTestCase
{
    use DatabaseTransactions;

    private UpdateUserAction $updateUserAction;

    private UserService $userService;

    private Division $division1;

    private Division $division2;

    private Financer $financerDiv1;

    private Financer $financerDiv2;

    private User $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Set default guard
        config(['auth.defaults.guard' => 'api']);

        // Create services
        $this->userService = app(UserService::class);
        $profileImageService = app(UserProfileImageService::class);
        $this->updateUserAction = new UpdateUserAction($this->userService, $profileImageService);

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

        // Create user and attach to financer in division 1
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
    public function it_prevents_adding_financer_from_different_division(): void
    {
        // Arrange
        $validatedData = [
            'financers' => [
                ['id' => $this->financerDiv1->id], // Keep existing financer
                ['id' => $this->financerDiv2->id], // Try to add financer from division 2
            ],
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot add financer from different division');

        $this->updateUserAction->handle($this->testUser, $validatedData);
    }

    #[Test]
    public function it_allows_adding_financer_from_same_division(): void
    {
        // Arrange - Create another financer in division 1
        $anotherFinancerDiv1 = Financer::factory()->create([
            'name' => 'Another Financer Division 1',
            'division_id' => $this->division1->id,
        ]);

        $validatedData = [
            'financers' => [
                ['id' => $this->financerDiv1->id], // Keep existing
                ['id' => $anotherFinancerDiv1->id], // Add new from same division
            ],
        ];

        // Act
        $result = $this->updateUserAction->handle($this->testUser, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(2, $result->financers);
        $financerIds = $result->financers->pluck('id')->toArray();
        $this->assertContains($this->financerDiv1->id, $financerIds);
        $this->assertContains($anotherFinancerDiv1->id, $financerIds);
    }

    #[Test]
    public function it_allows_god_role_to_add_financer_from_any_division(): void
    {
        // Arrange - Create a GOD user as the authenticated user
        $godUser = $this->createAuthUser(RoleDefaults::GOD);
        $this->actingAs($godUser);

        $validatedData = [
            'financers' => [
                ['id' => $this->financerDiv1->id],
                ['id' => $this->financerDiv2->id], // From different division
            ],
        ];

        // Act
        $result = $this->updateUserAction->handle($this->testUser, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(2, $result->financers);
        $financerIds = $result->financers->pluck('id')->toArray();
        $this->assertContains($this->financerDiv1->id, $financerIds);
        $this->assertContains($this->financerDiv2->id, $financerIds);
    }

    #[Test]
    public function it_allows_hexeko_admin_to_add_financer_from_any_division(): void
    {
        // Arrange - Create a HEXEKO_ADMIN user as the authenticated user
        $adminUser = $this->createAuthUser(RoleDefaults::HEXEKO_ADMIN);
        $this->actingAs($adminUser);

        $validatedData = [
            'financers' => [
                ['id' => $this->financerDiv1->id],
                ['id' => $this->financerDiv2->id], // From different division
            ],
        ];

        // Act
        $result = $this->updateUserAction->handle($this->testUser, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(2, $result->financers);
    }

    #[Test]
    public function it_maintains_existing_financers_when_no_financers_in_update(): void
    {
        // Arrange
        $validatedData = [
            'first_name' => 'Updated Name',
            // No financers key
        ];

        // Act
        $result = $this->updateUserAction->handle($this->testUser, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertCount(1, $result->financers);
        $this->assertEquals($this->financerDiv1->id, $result->financers->first()->id);
    }

    #[Test]
    public function it_prevents_removing_all_financers_from_user(): void
    {
        // Arrange
        $validatedData = [
            'financers' => [], // Try to remove all financers
        ];

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('User must have at least one financer');

        $this->updateUserAction->handle($this->testUser, $validatedData);
    }
}
