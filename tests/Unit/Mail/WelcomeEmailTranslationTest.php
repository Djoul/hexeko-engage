<?php

namespace Tests\Unit\Mail;

use App\Mail\WelcomeEmail;
use App\Models\Financer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('mail')]
#[Group('email')]
#[Group('notification')]
class WelcomeEmailTranslationTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_uses_invited_user_financer_language_when_invited_user_id_provided(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'language' => 'fr-FR',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'French Company',
            'available_languages' => ['fr-FR', 'en-GB'],
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'invited@example.com',
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'locale' => 'fr-FR',
            ]);

        // Act
        $mailable = new WelcomeEmail($invitedUser, $invitedUser->id);
        $mailable->build(); // Build the email to set the subject

        // Assert
        $this->assertEquals('fr-FR', $mailable->emailLocale);
        $this->assertEquals('Bienvenue sur UpPlus+ !', $mailable->subject);
    }

    #[Test]
    public function it_uses_portuguese_for_portuguese_invited_user(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Portuguese Division',
            'language' => 'pt-PT',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Portuguese Company',
            'available_languages' => ['pt-PT'],
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'joao@example.com',
                'first_name' => 'João',
                'last_name' => 'Silva',
            ]);

        $tempUser = (object) [
            'email' => 'joao@example.com',
            'first_name' => 'João',
        ];

        // Act
        $mailable = new WelcomeEmail($tempUser, $invitedUser->id);
        $mailable->build(); // Build the email to set the subject

        // Assert
        $this->assertEquals('pt-PT', $mailable->emailLocale);
        $this->assertEquals('Bem-vindo à UpPlus+ !', $mailable->subject);
    }

    #[Test]
    public function it_uses_english_for_english_invited_user(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'UK Division',
            'language' => 'en-GB',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'UK Company',
            'available_languages' => ['en-GB'],
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'john@example.com',
                'first_name' => 'John',
                'last_name' => 'Smith',
            ]);

        $tempUser = (object) [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'locale' => 'en-GB',
        ];

        // Act
        $mailable = new WelcomeEmail($tempUser, $invitedUser->id);
        $mailable->build(); // Build the email to set the subject

        // Assert
        $this->assertEquals('en-GB', $mailable->emailLocale);
        $this->assertEquals('Welcome to UpPlus+!', $mailable->subject);
    }

    #[Test]
    public function it_uses_dutch_for_dutch_invited_user(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Dutch Division',
            'language' => 'nl-NL',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Dutch Company',
            'available_languages' => ['nl-NL'],
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'jan@example.com',
                'first_name' => 'Jan',
                'last_name' => 'de Vries',
            ]);

        $tempUser = (object) [
            'email' => 'jan@example.com',
            'first_name' => 'Jan',
        ];

        // Act
        $mailable = new WelcomeEmail($tempUser, $invitedUser->id);
        $mailable->build(); // Build the email to set the subject

        // Assert
        $this->assertEquals('nl-NL', $mailable->emailLocale);
        $this->assertEquals('Welkom bij UpPlus+!', $mailable->subject);
    }

    #[Test]
    public function it_uses_belgian_french_for_belgian_invited_user(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Belgian Division',
            'language' => 'fr-BE',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Belgian Company',
            'available_languages' => ['fr-BE', 'nl-BE'],
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'pierre@example.com',
                'first_name' => 'Pierre',
                'last_name' => 'Dubois',
                'locale' => 'fr-BE',
            ]);

        // Act
        $mailable = new WelcomeEmail($invitedUser, $invitedUser->id);
        $mailable->build(); // Build the email to set the subject

        // Assert
        $this->assertEquals('fr-BE', $mailable->emailLocale);
        $this->assertEquals('Bienvenue sur UpPlus+ !', $mailable->subject);
    }

    #[Test]
    public function it_falls_back_to_default_locale_when_no_financer_and_no_user_locale(): void
    {
        // Arrange
        $user = (object) [
            'email' => 'test@example.com',
            'first_name' => 'Test',
        ];

        // Act
        $mailable = new WelcomeEmail($user);
        $mailable->build();

        // Assert
        $this->assertEquals('fr-FR', $mailable->emailLocale);
        $this->assertEquals('Bienvenue sur UpPlus+ !', $mailable->subject);
    }

    #[Test]
    public function it_uses_first_active_financer_when_user_has_multiple_financers(): void
    {
        // Arrange
        $divisionFr = ModelFactory::createDivision([
            'name' => 'French Division',
            'language' => 'fr-FR',
        ]);

        $divisionEn = ModelFactory::createDivision([
            'name' => 'English Division',
            'language' => 'en-GB',
        ]);

        $financer1 = Financer::factory()->create([
            'division_id' => $divisionFr->id,
            'name' => 'French Company',
        ]);
        $financer1->update(['available_languages' => ['fr-FR']]);

        $financer2 = Financer::factory()->create([
            'division_id' => $divisionEn->id,
            'name' => 'English Company',
        ]);
        $financer2->update(['available_languages' => ['en-GB']]);

        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'first_name' => 'Multi',
            'locale' => 'fr-FR',
            'financers' => [
                ['financer' => $financer1, 'active' => true, 'language' => 'fr-FR'],
                ['financer' => $financer2, 'active' => false, 'language' => 'en-GB'],
            ],
        ]);

        // Act
        $mailable = new WelcomeEmail($user);

        // Assert - should use the first active financer's language
        $this->assertEquals('fr-FR', $mailable->emailLocale);
    }

    #[Test]
    public function it_renders_email_content_in_correct_language(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'language' => 'fr-FR',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'French Company',
        ]);

        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'first_name' => 'Jean',
            'locale' => 'fr-FR',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'language' => 'fr-FR'],
            ],
        ]);

        // Act
        $mailable = new WelcomeEmail($user);
        $rendered = $mailable->render();

        // Assert - check that French content is rendered
        $this->assertStringContainsString('Bonjour, Jean', $rendered);
        $this->assertStringContainsString('Vous avez été invité(e) à rejoindre UpPlus', $rendered);
        $this->assertStringContainsString('inscrire', $rendered); // Without S' to avoid escaping issues
        $this->assertStringContainsString('équipe UpPlus+', $rendered); // Without L' to avoid escaping issues
    }

    #[Test]
    public function it_renders_portuguese_email_content(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Portuguese Division',
            'language' => 'pt-PT',
        ]);

        $financer = Financer::factory()->create([
            'division_id' => $division->id,
            'name' => 'Portuguese Company',
            'available_languages' => ['pt-PT'],
        ]);
        $financer->refresh(); // Reload to get updated available_languages

        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'first_name' => 'João',
            'locale' => 'pt-PT',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'language' => 'pt-PT'],
            ],
        ]);

        // Act
        $mailable = new WelcomeEmail($user);
        $rendered = $mailable->render();

        // Assert - check that Portuguese content is rendered
        $this->assertStringContainsString('Olá, João', $rendered);
        $this->assertStringContainsString('Recebeu um convite para se juntar', $rendered);
        $this->assertStringContainsString('Registe-se', $rendered);
        $this->assertStringContainsString('A Equipa UpPlus+', $rendered);
    }

    #[Test]
    public function it_includes_correct_url_for_invited_user(): void
    {
        // Arrange
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'language' => 'fr-FR',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'Test Company',
        ]);

        // Create invited user with financer metadata
        $invitedUser = User::factory()
            ->invited()
            ->withInvitationMetadata(['financer_id' => $financer->id])
            ->create([
                'email' => 'test@example.com',
                'first_name' => 'Test',
                'last_name' => 'User',
            ]);

        $tempUser = (object) [
            'email' => 'test@example.com',
            'first_name' => 'Test',
        ];

        // Act
        $mailable = new WelcomeEmail($tempUser, $invitedUser->id);
        $rendered = $mailable->render();

        // Assert
        $this->assertStringContainsString('/invited-user/'.$invitedUser->id, $rendered);
    }

    #[Test]
    public function it_prioritizes_pivot_language_over_financer_default_language(): void
    {
        // Arrange - Financer has French as default, but user prefers Portuguese in pivot
        $division = ModelFactory::createDivision([
            'name' => 'Test Division',
            'language' => 'fr-FR',
        ]);

        $financer = ModelFactory::createFinancer([
            'division_id' => $division->id,
            'name' => 'French Company',
        ]);

        // Create user with Portuguese preference in pivot table
        $user = ModelFactory::createUser([
            'email' => 'test@example.com',
            'first_name' => 'João',
            'locale' => 'en-GB', // User locale is English
            'financers' => [
                ['financer' => $financer, 'active' => true],
            ],
        ]);

        // Update pivot to set Portuguese as preferred language
        $user->financers()->updateExistingPivot($financer->id, [
            'language' => 'pt-PT',
        ]);

        // Reload to get fresh pivot data
        $user->refresh();

        // Act
        $mailable = new WelcomeEmail($user);
        $mailable->build();

        // Assert - Should use pivot language (pt-PT), not financer default (fr-FR) or user locale (en-GB)
        $this->assertEquals('pt-PT', $mailable->emailLocale);
        $this->assertEquals('Bem-vindo à UpPlus+ !', $mailable->subject);
    }
}
