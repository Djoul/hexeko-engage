<?php

namespace Tests\Feature\Http\Controllers\V1;

use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class MergeUserControllerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_merges_invited_user_with_existing_user(): void
    {
        // Arrange
        $financer = Financer::factory()->create();
        $user = User::factory()->create();
        $invitedUser = ModelFactory::createUser([
            'email' => 'invited@test.com',
            'phone' => '+33699999999',
            'sirh_id' => 'EXTERNAL_MERGE',
            'invitation_status' => 'pending',
            'financers' => [
                [
                    'financer' => $financer,
                    'active' => false,
                ],
            ],
        ]);

        // Act
        $response = $this->postJson('/api/v1/merge-user', [
            'email' => $user->email,
            'invited_user_id' => $invitedUser->id,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_OK);
        $response->assertJsonStructure([
            'message',
            'data',
        ]);

        // Assert that the financer is attached to the user
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'active' => 1,
        ]);

        // Assert that the invited user is soft deleted (User model uses SoftDeletes)
        $this->assertSoftDeleted('users', [
            'id' => $invitedUser->id,
        ]);
    }

    #[Test]
    public function it_returns_422_when_missing_fields(): void
    {
        // Act - Missing email
        $response1 = $this->postJson('/api/v1/merge-user', [
            'invited_user_id' => 'some-uuid',
        ]);

        // Assert
        $response1->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response1->assertJsonValidationErrors(['email']);

        // Act - Missing invited_user_id
        $response2 = $this->postJson('/api/v1/merge-user', [
            'email' => 'test@example.com',
        ]);

        // Assert
        $response2->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response2->assertJsonValidationErrors(['invited_user_id']);
    }

    #[Test]
    public function it_returns_404_when_invited_user_not_found(): void
    {
        // Arrange
        $user = User::factory()->create();
        $nonExistentUuid = '00000000-0000-0000-0000-000000000000';

        // Act
        $response = $this->postJson('/api/v1/merge-user', [
            'email' => $user->email,
            'invited_user_id' => $nonExistentUuid,
        ]);

        // Assert
        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonValidationErrors(['invited_user_id']);
    }
}
