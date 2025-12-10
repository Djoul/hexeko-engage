<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Role\CreateRoleAction;
use App\Models\Role;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('role')]

#[Group('validation')]
class RoleFormRequestTest extends ProtectedRouteTestCase
{
    protected $createRoleAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createRoleAction = Mockery::mock(CreateRoleAction::class);
        $this->app->instance(CreateRoleAction::class, $this->createRoleAction);

    }

    public static function roleDataProvider(): array
    {
        return [
            'valid_data' => [
                'data' => [
                    'id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                    'team_id' => 'self::$team->id',
                    'name' => 'Valid Role Name',
                    'guard_name' => 'api',
                    'created_at' => '2023-01-01 00:00:00',
                    'updated_at' => '2023-01-01 00:00:00',
                ],
                'expected' => true,
            ],
            'invalid_field1' => [
                'data' => [
                    'id' => null,
                    'team_id' => 'invalid-uuid',
                    'name' => '',
                    'guard_name' => 'api',
                    'created_at' => '2023-01-01 00:00:00',
                    'updated_at' => '2023-01-01 00:00:00',
                ],
                'expected' => false,
            ],
            'invalid_field2' => [
                'data' => [
                    'id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
                    'team_id' => 'self::$team->id',
                    'name' => str_repeat('a', 256),
                    'guard_name' => '',
                    'created_at' => 'invalid-date',
                    'updated_at' => null,
                ],
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('roleDataProvider')]
    public function test_role_form_request(array $data, bool $expected): void
    {
        if ($expected) {
            $team = ModelFactory::createTeam();

            setPermissionsTeamId($team->id);
            $data['team_id'] = $team->id;

            $this->createRoleAction
                ->shouldReceive('handle')
                ->once()
                ->with($data)
                ->andReturn(Role::factory()->create($data));
        }

        $response = $this->post('/api/v1/roles', $data, [
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
