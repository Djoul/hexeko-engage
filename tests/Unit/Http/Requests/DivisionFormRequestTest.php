<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Division\CreateDivisionAction;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('division')]
#[Group('validation')]
class DivisionFormRequestTest extends ProtectedRouteTestCase
{
    protected $createDivisionAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createDivisionAction = Mockery::mock(CreateDivisionAction::class);
        $this->app->instance(CreateDivisionAction::class, $this->createDivisionAction);

        //        $this->createRoleAndPermissions(RoleDefaults::HEXEKO_SUPER_ADMIN);

        //        $this->createAuthUser(RoleDefaults::HEXEKO_SUPER_ADMIN);

    }

    public static function divisionDataProvider(): array
    {

        return [
            'valid_data' => [
                'data' => [
                    'name' => 'Division 1',
                    'remarks' => 'This is a division.',
                    'country' => 'FR',
                    'currency' => 'EUR',
                    'timezone' => 'Europe/Paris',
                    'language' => 'fr-FR',
                ],
                'expected' => true,
            ],
            'valid_data_empty_description' => [
                'data' => [
                    'name' => 'Division 1',
                    'remarks' => null,
                    'country' => 'FR',
                    'currency' => 'EUR',
                    'timezone' => 'Europe/Paris',
                    'language' => 'fr-FR',
                ],
                'expected' => true,
            ],

            'invalid_name' => [
                'data' => [
                    'remarks' => 'This is a division.',
                    'country' => 'FR',
                    'currency' => 'EUR',
                    'timezone' => 'Europe/Paris',
                    'language' => 'fr-FR',
                ],
                'expected' => false,
            ],

            'invalid_country' => [
                'data' => [
                    'name' => 'Division 1',
                    'remarks' => 'This is a division.',
                    'currency' => 'EUR',
                    'timezone' => 'Europe/Paris',
                    'language' => 'fr-FR',
                ],
                'expected' => false,
            ],

        ];
    }

    #[DataProvider('divisionDataProvider')]
    public function test_division_form_request(array $data, bool $expected): void
    {
        if ($expected) {

            $this->createDivisionAction
                ->shouldReceive('handle')
                ->once()
                ->with($data)
                ->andReturn(ModelFactory::createDivision($data));
            //                ->andReturn(Division::factory()->create($data));
        }

        $response = $this->post('/api/v1/divisions', $data, [
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
