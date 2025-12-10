<?php

namespace Tests\Feature\Http\Controllers\V1\IntegrationController;

use App\Actions\Integration\CreateIntegrationAction;
use App\Enums\Integrations\IntegrationTypes;
use App\Models\Integration;
use App\Models\Module;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\ProtectedRouteTestCase;

#[Group('integration')]
class IntegrationFormRequestTest extends ProtectedRouteTestCase
{
    protected $createIntegrationAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createIntegrationAction = Mockery::mock(CreateIntegrationAction::class);
        $this->app->instance(CreateIntegrationAction::class, $this->createIntegrationAction);
    }

    public static function integrationDataProvider(): array
    {
        return [
            // Valid Data Case
            'valid_data' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000', // UUID
                    'name' => 'Integration Name',
                    'type' => IntegrationTypes::THIRD_PARTY_API,
                    'description' => 'A valid description of the integration.',
                    'active' => true,
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => null,
                ],
                'expected' => true,
            ],

            // Invalid ID (Not a UUID)
            'invalid_id' => [
                'data' => [
                    'id' => 'invalid-id',
                    'name' => 'Valid Name',
                    'description' => 'Valid description',
                    'active' => true,
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => null,
                ],
                'expected' => false,
            ],

            // Missing Required Name
            'missing_name' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'description' => 'Valid description',
                    'active' => true,
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => null,
                ],
                'expected' => false,
            ],

            // Invalid Active Field (Not a boolean)
            'invalid_active' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'name' => 'Valid Name',
                    'description' => 'Valid description',
                    'active' => 'not_boolean',
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => null,
                ],
                'expected' => false,
            ],

            // Invalid Created_at (Not a date)
            'invalid_created_at' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'name' => 'Valid Name',
                    'description' => 'Valid description',
                    'active' => true,
                    'created_at' => 'invalid-date',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => null,
                ],
                'expected' => false,
            ],

            // Invalid Deleted_at (Not a date or null)
            'invalid_deleted_at' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000',
                    'name' => 'Valid Name',
                    'description' => 'Valid description',
                    'active' => true,
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => 'invalid-date',
                ],
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('integrationDataProvider')]
    public function test_integration_form_request(array $data, bool $expected): void
    {
        if ($expected) {
            $module = Module::factory()->create();
            $data['module_id'] = $module->id;

            $this->createIntegrationAction
                ->shouldReceive('handle')
                ->once()
                ->with($data)
                ->andReturn(Integration::factory()->create($data));
        }

        $response = $this->post('/api/v1/integrations', $data, [
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
