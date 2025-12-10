<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\CreateUserInvitationFormRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for CreateUserInvitationFormRequest.
 * Sprint 3 - API Integration: Validates invitation creation request data.
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('form-requests')]
class CreateUserInvitationFormRequestTest extends TestCase
{
    use DatabaseTransactions;

    private CreateUserInvitationFormRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CreateUserInvitationFormRequest;
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $data = [
            'email' => 'newuser@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_requires_email_field(): void
    {
        $data = [
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->messages());
    }

    #[Test]
    public function it_requires_valid_email_format(): void
    {
        $data = [
            'email' => 'invalid-email',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('email', $validator->errors()->messages());
    }

    #[Test]
    public function it_requires_first_name_field(): void
    {
        $data = [
            'email' => 'user@test.com',
            'last_name' => 'Doe',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->messages());
    }

    #[Test]
    public function it_requires_last_name_field(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('last_name', $validator->errors()->messages());
    }

    #[Test]
    public function it_allows_optional_team_id(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'team_id' => '00000000-0000-0000-0000-000000000001',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_team_id_is_uuid(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'team_id' => 'invalid-uuid',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('team_id', $validator->errors()->messages());
    }

    #[Test]
    public function it_allows_optional_metadata(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'metadata' => ['department' => 'IT'],
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_metadata_is_array(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'metadata' => 'not-an-array',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('metadata', $validator->errors()->messages());
    }

    #[Test]
    public function it_allows_optional_expiration_days(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'expiration_days' => 14,
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_expiration_days_is_integer(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'expiration_days' => 'not-a-number',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expiration_days', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_expiration_days_minimum_value(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'expiration_days' => 0,
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expiration_days', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_expiration_days_maximum_value(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'expiration_days' => 31,
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('expiration_days', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_names_have_max_length(): void
    {
        $data = [
            'email' => 'user@test.com',
            'first_name' => str_repeat('a', 256),
            'last_name' => 'Doe',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('first_name', $validator->errors()->messages());
    }

    #[Test]
    public function it_authorizes_all_requests(): void
    {
        $this->assertTrue($this->request->authorize());
    }
}
