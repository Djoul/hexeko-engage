<?php

namespace Tests\Unit\Models\User;

use App\Repositories\Models\UserSettingsRepository;
use PHPUnit\Framework\Attributes\Group;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UserRepositoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_update_user_settings(): void
    {
        $user = ModelFactory::createUser(data: ['first_name' => 'User Test', 'locale' => 'en-GB']);

        $user->locale = 'fr-FR';

        $repository = new UserSettingsRepository;

        // Act
        $updatedUser = $repository->updateUserSettings($user);

        // Assert
        $this->assertEquals('fr-FR', $updatedUser->locale);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'locale' => 'fr-FR',
        ]);
    }
}
