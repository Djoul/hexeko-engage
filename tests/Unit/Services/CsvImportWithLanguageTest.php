<?php

namespace Tests\Unit\Services;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Services\Models\InvitedUserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('invited-user')]
#[Group('import')]
class CsvImportWithLanguageTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_creates_invited_user_with_language_via_service(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $adminUser = ModelFactory::createUser();
        $language = Languages::FRENCH;
        $email = 'invited.user.'.uniqid().'@example.com';

        // Act - Using createWithRole which is the proper way
        $user = app(InvitedUserService::class)->createWithRole([
            'first_name' => 'Jean',
            'last_name' => 'Dupont',
            'email' => $email,
            'phone' => '+33123456789',
            'financer_id' => $financer->id,
            'external_id' => 'EXT001',
            'language' => $language,
        ], RoleDefaults::BENEFICIARY, $adminUser->id);

        // Assert - User was created with correct locale
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'locale' => $language,
            'invitation_status' => 'pending',
        ]);

        // Assert - Financer pivot has correct language
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'language' => $language,
            'role' => RoleDefaults::BENEFICIARY,
        ]);
    }

    #[Test]
    public function it_creates_invited_user_without_language_using_default(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $adminUser = ModelFactory::createUser();
        $email = 'invited.user.nolang.'.uniqid().'@example.com';

        // Act - No language provided
        $user = app(InvitedUserService::class)->createWithRole([
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $email,
            'phone' => '',
            'financer_id' => $financer->id,
        ], RoleDefaults::BENEFICIARY, $adminUser->id);

        // Assert - User was created (locale uses DB default 'fr-FR')
        $this->assertDatabaseHas('users', [
            'email' => $email,
            'invitation_status' => 'pending',
        ]);

        // Assert - Financer pivot has null language
        $this->assertDatabaseHas('financer_user', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'language' => null,
        ]);

        // Assert - User locale defaults to fr-FR from DB
        $user->refresh();
        $this->assertEquals('fr-FR', $user->locale);
    }

    #[Test]
    public function it_supports_all_20_languages(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $adminUser = ModelFactory::createUser();
        $testLanguages = Languages::getValues();

        // Act & Assert - Test each language
        foreach ($testLanguages as $language) {
            $email = 'user.'.$language.'.'.uniqid().'@example.com';

            $user = app(InvitedUserService::class)->createWithRole([
                'first_name' => 'User',
                'last_name' => $language,
                'email' => $email,
                'financer_id' => $financer->id,
                'language' => $language,
            ], RoleDefaults::BENEFICIARY, $adminUser->id);

            $this->assertDatabaseHas('financer_user', [
                'user_id' => $user->id,
                'language' => $language,
            ]);
        }
    }
}
