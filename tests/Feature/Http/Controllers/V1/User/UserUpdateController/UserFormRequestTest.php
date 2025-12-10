<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserUpdateController;

use App\Models\User;
use Context;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Ramsey\Uuid\Uuid;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserFormRequestTest extends ProtectedRouteTestCase
{
    protected $createUserAction;

    protected static $financer;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure the users table is empty
        User::query()->delete();
        $this->auth = $this->createAuthUser();
    }

    public static function userDataProvider(): array
    {

        return [
            'valid_data' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'email' => 'valid@example.com',
                    'cognito_id' => Str::uuid()->toString(),
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'force_change_email' => true,
                    'birthdate' => '1990-01-01',
                    'terms_confirmed' => true,
                    'enabled' => true,
                    'locale' => 'en-GB',
                    'currency' => 'USD',
                    'timezone' => 'America/New_York',
                    'stripe_id' => 'stripe_123456',
                    'sirh_id' => 'ext_789',
                    'last_login' => now()->toISOString(),
                    'opt_in' => false,
                    'phone' => '1234567890',
                    'remember_token' => 'randomtoken123',
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString(),
                    'deleted_at' => null,
                    'gender' => 'male',
                ],
                'expected' => true,
            ],

            'invalid_email_format' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'email' => 'invalid-email-format', // Invalid email
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'force_change_email' => true,
                    'terms_confirmed' => true,
                    'enabled' => true,
                    'locale' => 'en-GB',
                    'currency' => 'USD',
                    'opt_in' => false,
                    'gender' => 'male',
                ],
                'expected' => false, // Should fail due to invalid email format
            ],

            'invalid_financer_format' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'email' => 'valid+2@example.com',
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'force_change_email' => true,
                    'terms_confirmed' => true,
                    'enabled' => true,
                    'locale' => 'en-GB',
                    'currency' => 'USD',
                    'opt_in' => false,
                    'financers' => [
                        ['id' => 'invalid-uuid', 'pivot' => ['active' => 'not_a_boolean']], // Invalid financer ID format and active field
                    ],
                    'gender' => 'male',
                ],
                'expected' => false, // Should fail due to invalid financer ID and pivot.active format
            ],

            'invalid_locale_length' => [
                'data' => [
                    'id' => Uuid::uuid4()->toString(),
                    'email' => 'valid+3@example.com',
                    'password' => 'password123', // pragma: allowlist secret
                    'password_confirmation' => 'password123', // pragma: allowlist secret
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'force_change_email' => true,
                    'terms_confirmed' => true,
                    'enabled' => true,
                    'locale' => 'english_us_long_text', // Invalid, exceeds max length of 5
                    'currency' => 'USD',
                    'opt_in' => false,
                    'gender' => 'male',
                ],
                'expected' => false, // Should fail due to locale exceeding max length
            ],
        ];
    }

    #[DataProvider('userDataProvider')]
    public function test_user_form_request(array $data, bool $expected): void
    {
        $financer = null;
        if ($expected) {
            // Mock the CreateCognitoUserAction so it does not execute
            Bus::fake();
            $financer = ModelFactory::createFinancer();
        }

        $initialUserCount = User::count();

        $headers = [
            'Accept' => 'application/json',
        ];
        Context::add('accessible_financers', $this->auth->financers->pluck('id')->toArray());

        $id = User::first()?->id;

        // Remove 'id' from the data array as it should not be updated
        unset($data['id']);

        $response = $this->actingAs($this->auth)->put('/api/v1/users/'.$id, $data, $headers);

        if ($expected) {
            $response->assertStatus(200);
        } else {
            $response->assertStatus(422);
        }

        // Validate the response errors if expected to fail.
        if ($expected) {
            $this->assertEmpty($response->json('errors'));
            // Assert that the job was dispatched
        } else {
            $this->assertNotEmpty($response->json('errors'));
        }

        // Assert that no new users were created
        $this->assertEquals($initialUserCount, User::count());
    }
}
