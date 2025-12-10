<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Financer\CreateFinancerAction;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('financer')]
#[Group('validation')]
class FinancerFormRequestTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Configure mock for successful validation cases only
     */
    protected function mockCreateFinancerAction(array $expectedData, mixed $returnValue): void
    {
        $mock = Mockery::mock(CreateFinancerAction::class);
        $mock->shouldReceive('handle')
            ->once()
            ->with($expectedData)
            ->andReturn($returnValue);

        $this->app->instance(CreateFinancerAction::class, $mock);
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: bool}>
     */
    public static function financerDataProvider(): array
    {
        return [
            'valid_data' => [
                'data' => [
                    'name' => 'Valid Name',
                    'external_id' => null,
                    'timezone' => 'UTC',
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    'division_id' => Str::uuid(),
                    'company_number' => 'TEST123456',
                ],
                'expected' => true,
            ],

            'missing_name' => [
                'data' => [
                    // name is missing
                    'external_id' => null,
                    'timezone' => 'UTC',
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    'division_id' => Str::uuid(),
                ],
                'expected' => false,
            ],

            'missing_timezone' => [
                'data' => [
                    'name' => 'Valid Name',
                    'external_id' => null,
                    // timezone is missing
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    'division_id' => Str::uuid(),
                ],
                'expected' => false,
            ],

            'missing_division_id' => [
                'data' => [
                    'name' => 'Valid Name',
                    'external_id' => null,
                    'timezone' => 'UTC',
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    // division_id is missing
                ],
                'expected' => false,
            ],

            'invalid_name_type' => [
                'data' => [
                    'name' => 123, // invalid type (integer)
                    'external_id' => null,
                    'timezone' => 'UTC',
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    'division_id' => Str::uuid(),
                ],
                'expected' => false,
            ],

            'invalid_timezone_type' => [
                'data' => [
                    'name' => 'Valid Name',
                    'external_id' => null,
                    'timezone' => 123, // invalid type (integer)
                    'registration_number' => null,
                    'registration_country' => null,
                    'website' => null,
                    'iban' => null,
                    'vat_number' => null,
                    'representative_id' => null,
                    'division_id' => Str::uuid(),
                ],
                'expected' => false,
            ],

            // Add more tests for other fields and edge cases as needed.
        ];
    }

    #[DataProvider('financerDataProvider')]
    public function test_financer_form_request(array $data, bool $expected): void
    {
        if ($expected) {
            $division = ModelFactory::createDivision();
            $data['division_id'] = $division->id;

            // Mock only for successful cases
            $this->mockCreateFinancerAction($data, ModelFactory::createFinancer($data));
        }

        $response = $this->post('/api/v1/financers', $data, [
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

    #[Test]
    public function it_validates_status_field(): void
    {
        $division = ModelFactory::createDivision();

        // Test valid statuses
        $validStatuses = ['active', 'pending', 'archived'];
        foreach ($validStatuses as $status) {
            $data = [
                'name' => 'Test Financer',
                'timezone' => 'UTC',
                'division_id' => $division->id,
                'status' => $status,
                'company_number' => 'TEST123456',
            ];

            $this->mockCreateFinancerAction($data, ModelFactory::createFinancer($data));

            $response = $this->post('/api/v1/financers', $data, [
                'Accept' => 'application/json',
            ]);

            $response->assertStatus(201);
        }

        // Test invalid status
        $data = [
            'name' => 'Test Financer',
            'timezone' => 'UTC',
            'division_id' => $division->id,
            'status' => 'invalid_status',
            'company_number' => 'TEST123456',
        ];

        $response = $this->post('/api/v1/financers', $data, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['status']);
    }

    #[Test]
    public function it_validates_bic_field(): void
    {
        $division = ModelFactory::createDivision();

        // Test valid BIC formats (8 and 11 characters)
        $validBics = ['BNPAFRPP', 'BNPAFRPPXXX'];
        foreach ($validBics as $bic) {
            $data = [
                'name' => 'Test Financer',
                'timezone' => 'UTC',
                'division_id' => $division->id,
                'bic' => $bic,
                'company_number' => 'TEST123456',
            ];

            $this->mockCreateFinancerAction($data, ModelFactory::createFinancer($data));

            $response = $this->post('/api/v1/financers', $data, [
                'Accept' => 'application/json',
            ]);

            $response->assertStatus(201);
        }

        // Test nullable BIC
        $data = [
            'name' => 'Test Financer',
            'timezone' => 'UTC',
            'division_id' => $division->id,
            'bic' => null,
            'company_number' => 'TEST123456',
        ];

        $this->mockCreateFinancerAction($data, ModelFactory::createFinancer($data));

        $response = $this->post('/api/v1/financers', $data, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        // Test invalid BIC formats
        $invalidBics = ['BNPA', 'BNPAFRPPXXXX', 'bnpafrpp', '12345678', 'BNPAFR1234'];
        foreach ($invalidBics as $bic) {
            $data = [
                'name' => 'Test Financer',
                'timezone' => 'UTC',
                'division_id' => $division->id,
                'bic' => $bic,
                'company_number' => 'TEST123456',
            ];

            $response = $this->post('/api/v1/financers', $data, [
                'Accept' => 'application/json',
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['bic']);
        }
    }

    #[Test]
    public function it_validates_company_number_field(): void
    {
        $division = ModelFactory::createDivision();

        // Test valid company number
        $data = [
            'name' => 'Test Financer',
            'timezone' => 'UTC',
            'division_id' => $division->id,
            'company_number' => 'TEST123456',
        ];

        $this->mockCreateFinancerAction($data, ModelFactory::createFinancer($data));

        $response = $this->post('/api/v1/financers', $data, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201);

        // Test missing company number (optional field - should pass)
        $dataWithoutCompanyNumber = [
            'name' => 'Test Financer 2',
            'timezone' => 'UTC',
            'division_id' => $division->id,
        ];

        $this->mockCreateFinancerAction($dataWithoutCompanyNumber, ModelFactory::createFinancer($dataWithoutCompanyNumber));

        $response = $this->post('/api/v1/financers', $dataWithoutCompanyNumber, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(201); // Should succeed since company_number is optional

        // Test empty string company number (should fail validation - string validation expects non-empty string)
        $dataWithEmptyCompanyNumber = [
            'name' => 'Test Financer 3',
            'timezone' => 'UTC',
            'division_id' => $division->id,
            'company_number' => '',
        ];

        $response = $this->post('/api/v1/financers', $dataWithEmptyCompanyNumber, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_number']);

        // Test company number exceeding max length
        $data = [
            'name' => 'Test Financer',
            'timezone' => 'UTC',
            'division_id' => $division->id,
            'company_number' => str_repeat('A', 256),
        ];

        $response = $this->post('/api/v1/financers', $data, [
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['company_number']);
    }
}
