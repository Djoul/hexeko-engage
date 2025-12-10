<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Module\CreateModuleAction;
use App\Enums\ModulesCategories;
use App\Models\Module;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\ProtectedRouteTestCase;

#[Group('module')]
class ModuleFormRequestTest extends ProtectedRouteTestCase
{
    protected $createModuleAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createModuleAction = Mockery::mock(CreateModuleAction::class);
        $this->app->instance(CreateModuleAction::class, $this->createModuleAction);
    }

    public static function moduleDataProvider(): array
    {
        return [
            // Valid Data Case
            'valid_data' => [
                'data' => [
                    'id' => '550e8400-e29b-41d4-a716-446655440000', // UUID
                    'name' => [
                        'fr-FR' => 'Module Name',
                        'en-US' => 'Module Name',
                    ],
                    'description' => [
                        'fr-FR' => 'A valid description of the module.',
                        'en-US' => 'A valid description of the module.',
                    ],
                    'active' => true,
                    'category' => ModulesCategories::WELLBEING,
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
                    'name' => [
                        'fr-FR' => 'Valid Name',
                        'en-US' => 'Valid Name',
                    ],
                    'description' => [
                        'fr-FR' => 'Valid description',
                        'en-US' => 'Valid description',
                    ],
                    'category' => ModulesCategories::WELLBEING,
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
                    'description' => [
                        'fr-FR' => 'Valid description',
                        'en-US' => 'Valid description',
                    ],
                    'category' => ModulesCategories::WELLBEING,
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
                    'name' => [
                        'fr-FR' => 'Valid Name',
                        'en-US' => 'Valid Name',
                    ],
                    'description' => [
                        'fr-FR' => 'Valid description',
                        'en-US' => 'Valid description',
                    ],
                    'category' => ModulesCategories::WELLBEING,
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
                    'name' => [
                        'fr-FR' => 'Valid Name',
                        'en-US' => 'Valid Name',
                    ],
                    'description' => [
                        'fr-FR' => 'Valid description',
                        'en-US' => 'Valid description',
                    ],
                    'category' => ModulesCategories::WELLBEING,
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
                    'name' => [
                        'fr-FR' => 'Valid Name',
                        'en-US' => 'Valid Name',
                    ],
                    'description' => [
                        'fr-FR' => 'Valid description',
                        'en-US' => 'Valid description',
                    ],
                    'category' => ModulesCategories::WELLBEING,
                    'active' => true,
                    'created_at' => '2024-03-06 12:00:00',
                    'updated_at' => '2024-03-06 12:30:00',
                    'deleted_at' => 'invalid-date',
                ],
                'expected' => false,
            ],
        ];
    }

    #[DataProvider('moduleDataProvider')]
    public function test_module_form_request(array $data, bool $expected): void
    {
        if ($expected) {

            $this->createModuleAction
                ->shouldReceive('handle')
                ->once()
                ->with($data)
                ->andReturn(Module::factory()->create($data));
        }

        $response = $this->post('/api/v1/modules', $data, [
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
