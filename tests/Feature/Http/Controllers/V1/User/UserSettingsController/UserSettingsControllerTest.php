<?php

namespace Tests\Feature\Http\Controllers\V1\User\UserSettingsController;

use App\Enums\Languages;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserSettingsControllerTest extends ProtectedRouteTestCase
{
    #[Test]
    public function test_change_user_settings_action_succeeds(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser(data: [
            'first_name' => 'User Test',
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Set financer context (as string ID)
        Context::add('financer_id', $financer->id);

        // Act
        $response = $this->actingAs($user)
            ->putJson('/api/v1/users/'.$user->id.'/settings', [
                'locale' => Languages::FRENCH,
            ]);

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'user',
            ]);

        // Check pivot table was updated
        $pivot = DB::table('financer_user')
            ->where('user_id', $user->id)
            ->where('financer_id', $financer->id)
            ->first();

        $this->assertEquals(Languages::FRENCH, $pivot->language);

        // Check user locale was also synced
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => Languages::FRENCH,
        ]);
    }

    #[Test]
    public function test_change_user_settings_action_with_invalid_data(): void
    {
        $user = ModelFactory::createUser(data: [
            'first_name' => 'User Test',
            'locale' => Languages::ENGLISH,
        ]);

        // Act
        $response = $this->actingAs($user)
            ->putJson('/api/v1/users/'.$user->id.'/settings', [
                'locale' => 'francais de france',
            ]);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['locale']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => Languages::ENGLISH,
        ]);
    }

    protected function tearDown(): void
    {
        Context::flush();
        parent::tearDown();
    }
}
