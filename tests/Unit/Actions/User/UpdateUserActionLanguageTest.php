<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User;

use App\Actions\User\CRUD\UpdateUserAction;
use App\Enums\Languages;
use App\Models\User;
use App\Services\Models\UserProfileImageService;
use App\Services\Models\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
class UpdateUserActionLanguageTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateUserAction $action;

    private MockInterface $userService;

    private MockInterface $profileImageService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userService = Mockery::mock(UserService::class);
        $this->profileImageService = Mockery::mock(UserProfileImageService::class);

        $this->action = new UpdateUserAction(
            $this->userService,
            $this->profileImageService
        );
    }

    #[Test]
    public function it_updates_language_via_user_service_when_locale_provided(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer = ModelFactory::createFinancer(['division_id' => $division->id]);
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        Context::add('financer_id', $financer->id);

        $validatedData = [
            'locale' => Languages::FRENCH,
            'first_name' => 'Updated Name',
        ];

        // Expect language update via UserService
        $this->userService
            ->shouldReceive('updateUserLanguage')
            ->once()
            ->with($user, Languages::FRENCH);

        // Expect other field updates
        $this->userService
            ->shouldReceive('update')
            ->once()
            ->with($user, ['first_name' => 'Updated Name']);

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_handles_update_without_locale_change(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        $validatedData = [
            'first_name' => 'Updated Name',
            'last_name' => 'Updated Last',
        ];

        // Should NOT call updateUserLanguage
        $this->userService
            ->shouldNotReceive('updateUserLanguage');

        // Should call update with all data
        $this->userService
            ->shouldReceive('update')
            ->once()
            ->with($user, $validatedData);

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_updates_language_and_handles_profile_image(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        $validatedData = [
            'locale' => Languages::GERMAN,
            'profile_image' => 'base64encodedimage',
        ];

        // Expect language update
        $this->userService
            ->shouldReceive('updateUserLanguage')
            ->once()
            ->with($user, Languages::GERMAN);

        // Expect profile image update
        $this->profileImageService
            ->shouldReceive('updateProfileImage')
            ->once()
            ->with($user, 'base64encodedimage');

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_handles_language_update_with_financers(): void
    {
        // Arrange
        $division = ModelFactory::createDivision();
        $financer1 = ModelFactory::createFinancer(['division_id' => $division->id]);
        $financer2 = ModelFactory::createFinancer(['division_id' => $division->id]);

        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
            'financers' => [
                ['financer' => $financer1, 'active' => true],
            ],
        ]);

        $validatedData = [
            'locale' => Languages::ITALIAN,
            'financers' => [
                ['id' => $financer2->id, 'pivot' => ['active' => true]],
            ],
        ];

        // Expect language update
        $this->userService
            ->shouldReceive('updateUserLanguage')
            ->once()
            ->with($user, Languages::ITALIAN);

        // Expect financer sync
        $this->userService
            ->shouldReceive('syncFinancers')
            ->once()
            ->with($user, $validatedData['financers']);

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_does_not_call_update_when_only_locale_provided(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        $validatedData = [
            'locale' => Languages::SPANISH,
        ];

        // Expect language update
        $this->userService
            ->shouldReceive('updateUserLanguage')
            ->once()
            ->with($user, Languages::SPANISH);

        // Should NOT call update since no other fields
        $this->userService
            ->shouldNotReceive('update');

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    #[Test]
    public function it_handles_null_locale_value(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'locale' => Languages::ENGLISH,
        ]);

        $validatedData = [
            'locale' => null,
            'first_name' => 'Updated',
        ];

        // Should NOT call updateUserLanguage for null
        $this->userService
            ->shouldNotReceive('updateUserLanguage');

        // Should call update with remaining data
        $this->userService
            ->shouldReceive('update')
            ->once()
            ->with($user, ['first_name' => 'Updated']);

        // Act
        $result = $this->action->handle($user, $validatedData);

        // Assert
        $this->assertInstanceOf(User::class, $result);
    }

    protected function tearDown(): void
    {
        Context::flush();
        Mockery::close();
        parent::tearDown();
    }
}
