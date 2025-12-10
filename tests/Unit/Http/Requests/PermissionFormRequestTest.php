<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Permission\CreatePermissionAction;
use App\Models\Permission;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('permission')]
#[Group('validation')]
class PermissionFormRequestTest extends ProtectedRouteTestCase
{
    protected $createPermissionAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createPermissionAction = Mockery::mock(CreatePermissionAction::class);
        $this->app->instance(CreatePermissionAction::class, $this->createPermissionAction);
    }

    public static function permissionDataProvider(): array
    {
        return [
            'valid_data' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'name' => 'Valid Permission Name 2',
                    'created_at' => '2023-12-01',
                    'updated_at' => '2023-12-02',
                ],
                'expected' => true,
            ],

            'no_name' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'created_at' => '2023-12-01',
                    'updated_at' => '2023-12-02',
                ],
                'expected' => false,
            ],

            'bad_guard_name' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'name' => 'Valid Permission Name',
                    'guard_name' => 'invalid_guard',
                    'created_at' => '2023-12-01',
                    'updated_at' => '2023-12-02',
                ],
                'expected' => false,
            ],

        ];
    }

    #[DataProvider('permissionDataProvider')]
    public function test_permission_form_request(array $data, bool $expected): void
    {
        if ($expected) {
            $data['name'] = 'Valid Permission Name'.now()->timestamp;
            $permission = Permission::factory()->create($data);

            $this->createPermissionAction
                ->shouldReceive('handle')
                ->once()
                ->withAnyArgs()
                ->andReturn($permission);
        }

        $response = $this->post('/api/v1/permissions', $data, [
            'Accept' => 'application/json',
        ]);

        if ($expected) {
            $response->assertStatus(201);
        } else {
            $response->assertStatus(422);
        }

        // Validate the response errors if expected to fail.
        if ($expected) {
            $this->assertEmpty($response->json('errors'));
        } else {
            $this->assertNotEmpty($response->json('errors'));
        }
    }
}
