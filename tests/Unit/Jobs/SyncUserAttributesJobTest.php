<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Enums\Gender;
use App\Jobs\SyncUserAttributesJob;
use App\Models\ContractType;
use App\Models\Department;
use App\Models\FinancerUser;
use App\Models\User;
use App\Services\Apideck\ApideckService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('jobs')]
#[Group('apideck')]
class SyncUserAttributesJobTest extends TestCase
{
    use DatabaseTransactions;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function it_returns_early_when_financer_user_has_no_sirh_id(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => null,
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $apideckService->shouldNotHaveReceived('getEmployee');
        $user->refresh();
        $this->assertNull($user->gender);
    }

    #[Test]
    public function it_returns_early_when_employee_data_is_empty(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create([
            'gender' => null,
            'birthdate' => null,
        ]);
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn(['data' => null]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $this->assertNull($user->gender);
        $this->assertNull($user->birthdate);
    }

    #[Test]
    public function it_updates_user_gender_when_valid(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create(['gender' => null]);
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'gender' => Gender::MALE,
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $this->assertEquals(Gender::MALE, $user->gender);
    }

    #[Test]
    public function it_does_not_update_gender_when_invalid(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create(['gender' => null]);
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'gender' => 'invalid-gender',
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $this->assertNull($user->gender);
    }

    #[Test]
    public function it_updates_user_birthdate(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create(['birthdate' => null]);
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $birthday = '1990-01-15';

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'birthday' => $birthday,
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $this->assertNotNull($user->birthdate);
        /** @var Carbon $birthdate */
        $birthdate = $user->birthdate;
        $this->assertEquals($birthday, $birthdate->format('Y-m-d'));
    }

    #[Test]
    public function it_updates_financer_user_started_at(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
            'started_at' => null,
        ]);

        $startDate = '2020-01-01';

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'employment_start_date' => $startDate,
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $financerUser = FinancerUser::where('financer_id', $financer->id)
            ->where('user_id', $user->id)
            ->first();
        $this->assertNotNull($financerUser);
        $this->assertEquals($startDate, $financerUser->started_at->format('Y-m-d'));
    }

    #[Test]
    public function it_creates_and_attaches_contract_type(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'employment_role' => [
                        'type' => 'full-time',
                    ],
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $contractType = ContractType::withoutGlobalScopes()->where('financer_id', $financer->id)
            ->where('apideck_id', 'full-time')
            ->first();

        $this->assertNotNull($contractType);
        $this->assertInstanceOf(ContractType::class, $contractType);
        $this->assertEquals('full-time', $contractType->apideck_id);

        // Verify the pivot table entry exists
        $this->assertDatabaseHas('contract_type_user', [
            'user_id' => $user->id,
            'contract_type_id' => $contractType->id,
        ]);

        $user->refresh();
        $user->load(['contractTypes' => function ($query): void {
            $query->withoutGlobalScopes();
        }]);
        $this->assertTrue($user->contractTypes->contains($contractType));
    }

    #[Test]
    public function it_uses_existing_contract_type_when_already_exists(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $existingContractType = ContractType::factory()->create([
            'financer_id' => $financer->id,
            'apideck_id' => 'full-time',
            'name' => [
                'fr-FR' => 'Existing Contract Type',
                'en-GB' => 'Existing Contract Type',
            ],
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'employment_role' => [
                        'type' => 'full-time',
                    ],
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $contractTypeCount = ContractType::withoutGlobalScopes()->where('financer_id', $financer->id)
            ->where('apideck_id', 'full-time')
            ->count();

        $this->assertEquals(1, $contractTypeCount);

        // Verify the pivot table entry exists
        $this->assertDatabaseHas('contract_type_user', [
            'user_id' => $user->id,
            'contract_type_id' => $existingContractType->id,
        ]);

        $user->refresh();
        $user->load(['contractTypes' => function ($query): void {
            $query->withoutGlobalScopes();
        }]);
        $this->assertTrue($user->contractTypes->contains($existingContractType));
    }

    #[Test]
    public function it_creates_and_attaches_department(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'department_id' => 'dept-123',
                    'department' => 'Engineering',
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $department = Department::withoutGlobalScopes()->where('financer_id', $financer->id)
            ->where('apideck_id', 'dept-123')
            ->first();

        $this->assertNotNull($department);
        $this->assertInstanceOf(Department::class, $department);
        $this->assertEquals('Engineering', $department->name);

        // Verify the pivot table entry exists
        $this->assertDatabaseHas('department_user', [
            'user_id' => $user->id,
            'department_id' => $department->id,
        ]);

        $user->refresh();
        $user->load(['departments' => function ($query): void {
            $query->withoutGlobalScopes();
        }]);

        $this->assertTrue($user->departments->contains($department));
    }

    #[Test]
    public function it_attaches_manager_when_manager_exists(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $manager = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);
        $manager->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'manager-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'manager' => [
                        'id' => 'manager-sirh-id',
                    ],
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $user->load('managers');
        $this->assertTrue($user->managers->contains($manager));
    }

    #[Test]
    public function it_does_not_attach_manager_when_manager_does_not_exist(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create();
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'manager' => [
                        'id' => 'non-existent-sirh-id',
                    ],
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $user->load('managers');
        $this->assertCount(0, $user->managers);
    }

    #[Test]
    public function it_updates_multiple_attributes_in_single_save(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = User::factory()->create([
            'gender' => null,
            'birthdate' => null,
        ]);
        $user->financers()->attach($financer->id, [
            'active' => true,
            'sirh_id' => 'test-sirh-id',
            'started_at' => null,
        ]);

        $apideckService = Mockery::mock(ApideckService::class);
        $apideckService->shouldReceive('initializeConsumerId')
            ->once()
            ->with($financer->id);
        $apideckService->shouldReceive('getEmployee')
            ->once()
            ->with('test-sirh-id')
            ->andReturn([
                'data' => [
                    'gender' => Gender::FEMALE,
                    'birthday' => '1990-05-20',
                    'employment_start_date' => '2020-01-01',
                ],
            ]);

        $job = new SyncUserAttributesJob($user, $financer->id);

        // Act
        $job->handle($apideckService);

        // Assert
        $user->refresh();
        $financerUser = FinancerUser::where('financer_id', $financer->id)
            ->where('user_id', $user->id)
            ->first();

        $this->assertEquals(Gender::FEMALE, $user->gender);
        $this->assertNotNull($user->birthdate);
        /** @var Carbon $birthdate */
        $birthdate = $user->birthdate;
        $this->assertEquals('1990-05-20', $birthdate->format('Y-m-d'));
        $this->assertNotNull($financerUser);
        $this->assertNotNull($financerUser->started_at);
        /** @var Carbon $startedAt */
        $startedAt = $financerUser->started_at;
        $this->assertEquals('2020-01-01', $startedAt->format('Y-m-d'));
    }
}
