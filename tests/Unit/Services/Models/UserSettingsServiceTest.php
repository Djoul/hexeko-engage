<?php

namespace Tests\Unit\Services\Models;

use App\Models\User;
use App\Repositories\Models\UserSettingsRepository;
use App\Services\Models\UserService;
use App\Services\Models\UserSettingsService;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UserSettingsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    #[Test]
    public function it_updates_user_locale_when_valid_locale_provided(): void
    {
        // Arrange
        $user = ModelFactory::createUser(data: ['locale' => 'en-GB']);
        $payload = ['locale' => 'fr-FR'];

        // The repository should not be called when only locale is present
        $userRepositoryMock = $this->mock(UserSettingsRepository::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('updateUserSettings');
        });

        $userServiceMock = $this->mock(UserService::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('updateUserLanguage')
                ->once()
                ->with($user, 'fr-FR');
        });

        $service = new UserSettingsService($userRepositoryMock, $userServiceMock);

        // Act
        $result = $service->changeUserSettings($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_ignores_non_string_locale_values(): void
    {
        // Arrange
        $user = ModelFactory::createUser(data: ['locale' => 'en-GB']);
        $payload = ['locale' => 123]; // Non-string value

        // Repository should not be called when locale is invalid
        $userRepositoryMock = $this->mock(UserSettingsRepository::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('updateUserSettings');
        });

        $userServiceMock = $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('updateUserLanguage');
        });

        $service = new UserSettingsService($userRepositoryMock, $userServiceMock);

        // Act
        $result = $service->changeUserSettings($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_handles_missing_locale_in_payload(): void
    {
        // Arrange
        $user = ModelFactory::createUser(data: ['locale' => 'en-GB']);
        $payload = ['other_setting' => 'value']; // No locale key

        $userRepositoryMock = $this->mock(UserSettingsRepository::class, function (MockInterface $mock) use ($user): void {
            $mock->shouldReceive('updateUserSettings')
                ->once()
                ->with($user)
                ->andReturn($user);
        });

        $userServiceMock = $this->mock(UserService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('updateUserLanguage');
        });

        $service = new UserSettingsService($userRepositoryMock, $userServiceMock);

        // Act
        $result = $service->changeUserSettings($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }
}
