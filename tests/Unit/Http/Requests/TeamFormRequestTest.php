<?php

namespace Tests\Unit\Http\Requests;

use App\Actions\Team\CreateTeamAction;
use App\Enums\IDP\TeamTypes;
use App\Http\Middleware\CognitoAuthMiddleware;
use App\Models\Team;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Tests\ProtectedRouteTestCase;

#[Group('team')]

#[Group('validation')]
class TeamFormRequestTest extends ProtectedRouteTestCase
{
    protected $createTeamAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createTeamAction = Mockery::mock(CreateTeamAction::class);
        $this->app->instance(CreateTeamAction::class, $this->createTeamAction);
    }

    public static function teamDataProvider(): array
    {
        return [
            'valid_data' => [
                'data' => [
                    'name' => 'Valid Team Name',
                    'slug' => 'valid-team-name',
                    'type' => TeamTypes::DIVISION,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                    'deleted_at' => null,
                ],
                'expected' => true,
            ],

            'missing_name' => [
                'data' => [
                    'slug' => 'missing-name',
                    'type' => TeamTypes::DIVISION,
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ],
                'expected' => false, // 'name' is required
            ],

            'type_invalid_length' => [
                'data' => [

                    'name' => 'Valid Name',
                    'slug' => 'valid-name',
                    'type' => 'ABCD', // More than 3 characters
                    'created_at' => now()->toDateTimeString(),
                    'updated_at' => now()->toDateTimeString(),
                ],
                'expected' => false, // 'type' must be exactly 3 characters
            ],
            'invalid_date_format' => [
                'data' => [

                    'name' => 'Valid Name',
                    'slug' => 'valid-name',
                    'type' => TeamTypes::DIVISION,
                    'created_at' => 'invalid-date',
                    'updated_at' => now()->toDateTimeString(),
                ],
                'expected' => false, // 'created_at' must be a valid date
            ],
        ];
    }

    #[DataProvider('teamDataProvider')]
    public function test_team_form_request(array $data, bool $expected): void
    {

        $this->withoutMiddleware(CognitoAuthMiddleware::class);
        $data['id'] = Uuid::uuid4()->toString();
        if ($expected) {
            $this->createTeamAction
                ->shouldReceive('handle')
                ->once()
                ->with($data)
                ->andReturn(Team::factory()->create($data));
        }

        $response = $this->post('/api/v1/teams', $data, [
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
