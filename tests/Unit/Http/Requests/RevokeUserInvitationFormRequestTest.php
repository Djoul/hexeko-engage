<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\RevokeUserInvitationFormRequest;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for RevokeUserInvitationFormRequest.
 * Sprint 3 - API Integration: Validates invitation revocation request data.
 */
#[Group('user')]
#[Group('invited-user')]
#[Group('form-requests')]
class RevokeUserInvitationFormRequestTest extends TestCase
{
    use DatabaseTransactions;

    private RevokeUserInvitationFormRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new RevokeUserInvitationFormRequest;
    }

    #[Test]
    public function it_passes_validation_with_valid_data(): void
    {
        $data = [
            'user_id' => '00000000-0000-0000-0000-000000000001',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_requires_user_id_field(): void
    {
        $data = [];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_user_id_is_uuid(): void
    {
        $data = [
            'user_id' => 'invalid-uuid',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('user_id', $validator->errors()->messages());
    }

    #[Test]
    public function it_allows_optional_reason(): void
    {
        $data = [
            'user_id' => '00000000-0000-0000-0000-000000000001',
            'reason' => 'Position filled',
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertFalse($validator->fails());
    }

    #[Test]
    public function it_validates_reason_is_string(): void
    {
        $data = [
            'user_id' => '00000000-0000-0000-0000-000000000001',
            'reason' => 12345,
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reason', $validator->errors()->messages());
    }

    #[Test]
    public function it_validates_reason_maximum_length(): void
    {
        $data = [
            'user_id' => '00000000-0000-0000-0000-000000000001',
            'reason' => str_repeat('a', 501),
        ];

        $validator = Validator::make($data, $this->request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('reason', $validator->errors()->messages());
    }

    #[Test]
    public function it_authorizes_all_requests(): void
    {
        $this->assertTrue($this->request->authorize());
    }
}
