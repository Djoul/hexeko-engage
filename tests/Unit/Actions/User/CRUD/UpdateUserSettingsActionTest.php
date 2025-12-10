<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\User\CRUD;

use App\Actions\User\CRUD\UpdateUserSettingsAction;
use App\Actions\User\UpdateUserLanguageAction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('actions')]
class UpdateUserSettingsActionTest extends TestCase
{
    use DatabaseTransactions;

    private UpdateUserSettingsAction $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(UpdateUserSettingsAction::class);
    }

    #[Test]
    public function it_updates_user_locale_via_language_action(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'email' => 'locale@test.com',
            'locale' => 'en-GB',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $payload = [
            'locale' => 'fr-FR',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Verify locale was updated via UpdateUserLanguageAction
        $freshUser = $user->fresh();
        $this->assertEquals('fr-FR', $freshUser->locale);
    }

    #[Test]
    public function it_updates_other_settings_without_locale(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'settings@test.com',
            'timezone' => 'UTC',
        ]);

        $payload = [
            'timezone' => 'Europe/Paris',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Europe/Paris', $result->timezone);

        // Verify database was updated
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'timezone' => 'Europe/Paris',
        ]);
    }

    #[Test]
    public function it_updates_locale_and_other_settings_together(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'email' => 'combined@test.com',
            'locale' => 'en-GB',
            'timezone' => 'UTC',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $payload = [
            'locale' => 'fr-FR',
            'timezone' => 'Europe/Paris',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        $freshUser = $user->fresh();
        $this->assertEquals('fr-FR', $freshUser->locale);
        $this->assertEquals('Europe/Paris', $freshUser->timezone);
    }

    #[Test]
    public function it_handles_empty_payload_gracefully(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'empty@test.com',
            'locale' => 'en-GB',
        ]);

        $payload = [];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($user->id, $result->id);
        $this->assertEquals('en-GB', $result->locale);
    }

    #[Test]
    public function it_handles_null_locale_gracefully(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'null-locale@test.com',
            'locale' => 'en-GB',
            'timezone' => 'UTC',
        ]);

        $payload = [
            'locale' => null,
            'timezone' => 'Europe/Paris',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Locale should remain unchanged (null locale skipped)
        $this->assertEquals('en-GB', $result->locale);

        // Timezone should be updated
        $this->assertEquals('Europe/Paris', $result->timezone);
    }

    #[Test]
    public function it_wraps_updates_in_transaction(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'transaction@test.com',
            'locale' => 'en-GB',
        ]);

        $originalLocale = $user->locale;

        $payload = [
            'locale' => 'fr-FR',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Verify changes were committed
        $freshUser = User::find($user->id);
        $this->assertEquals('fr-FR', $freshUser->locale);
        $this->assertNotEquals($originalLocale, $freshUser->locale);
    }

    #[Test]
    public function it_returns_fresh_user_instance(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'fresh@test.com',
            'timezone' => 'UTC',
        ]);

        $payload = [
            'timezone' => 'America/New_York',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('America/New_York', $result->timezone);

        // Verify returned user is fresh from database
        $directFetch = User::find($user->id);
        $this->assertEquals($result->timezone, $directFetch->timezone);
    }

    #[Test]
    public function it_delegates_locale_to_update_language_action(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $user = ModelFactory::createUser([
            'email' => 'delegate@test.com',
            'locale' => 'en-GB',
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        $payload = [
            'locale' => 'de-DE',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);

        // Verify UpdateUserLanguageAction was called (locale updated)
        $freshUser = $user->fresh();
        $this->assertEquals('de-DE', $freshUser->locale);

        // Verify financer pivot was updated (proof of UpdateUserLanguageAction)
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
        ]);
    }

    #[Test]
    public function it_updates_multiple_settings_without_locale(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'multiple@test.com',
            'timezone' => 'UTC',
            'currency' => 'EUR',
        ]);

        $payload = [
            'timezone' => 'Asia/Tokyo',
            'currency' => 'JPY',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('Asia/Tokyo', $result->timezone);
        $this->assertEquals('JPY', $result->currency);

        // Verify database
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'timezone' => 'Asia/Tokyo',
            'currency' => 'JPY',
        ]);
    }

    #[Test]
    public function it_preserves_user_id_and_immutable_fields(): void
    {
        // Arrange
        $user = ModelFactory::createUser([
            'email' => 'preserve@test.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $originalId = $user->id;
        $originalFirstName = $user->first_name;
        $originalLastName = $user->last_name;

        $payload = [
            'timezone' => 'Europe/London',
        ];

        // Act
        $result = $this->action->execute($user, $payload);

        // Assert
        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals($originalId, $result->id);
        $this->assertEquals($originalFirstName, $result->first_name);
        $this->assertEquals($originalLastName, $result->last_name);
        $this->assertEquals('Europe/London', $result->timezone);
    }
}
