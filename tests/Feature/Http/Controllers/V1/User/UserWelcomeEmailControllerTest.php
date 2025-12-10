<?php

namespace Tests\Feature\Http\Controllers\V1\User;

use App\Enums\IDP\RoleDefaults;
use App\Enums\IDP\TeamTypes;
use App\Http\Middleware\CheckPermissionAttribute;
use App\Mail\WelcomeEmail;
use App\Models\User;
use App\Services\CognitoService;
use Context;
use Illuminate\Support\Facades\Mail;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
class UserWelcomeEmailControllerTest extends ProtectedRouteTestCase
{
    public $user;

    protected $team;

    protected function setUp(): void
    {
        parent::setUp();

        // Clean up Laravel Context to ensure test isolation
        Context::flush();

        // Désactiver le middleware de vérification des permissions pour les tests
        $this->withoutMiddleware(CheckPermissionAttribute::class);

        // Créer une équipe et les rôles nécessaires
        $this->team = ModelFactory::createTeam(['type' => TeamTypes::GLOBAL]);
        ModelFactory::createRole(['name' => RoleDefaults::BENEFICIARY, 'team_id' => $this->team->id]);
        ModelFactory::createRole(['name' => RoleDefaults::HEXEKO_SUPER_ADMIN, 'team_id' => $this->team->id]);

        // Initialiser l'utilisateur authentifié avec le rôle HEXEKO_SUPER_ADMIN qui a toutes les permissions
        $this->user = $this->createAuthUser(RoleDefaults::HEXEKO_SUPER_ADMIN, $this->team);

        // S'assurer que l'utilisateur est authentifié pour les requêtes
        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // Clean up Laravel Context after each test
        Context::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_can_resend_welcome_email_with_temporary_password(): void
    {
        // Mock le service Cognito pour éviter les appels réels à AWS
        $cognitoServiceMock = Mockery::mock(CognitoService::class);
        $cognitoServiceMock->shouldReceive('resetPassword')
            ->once()
            ->andReturn(['success' => true]);
        $this->app->instance(CognitoService::class, $cognitoServiceMock);

        // Mock Mail pour vérifier l'envoi
        Mail::fake();

        // Créer un utilisateur de test
        $user = User::factory()->create([
            'cognito_id' => 'test-cognito-id', // pragma: allowlist secret
            'temp_password' => 'old-temp-password', // pragma: allowlist secret
        ]);

        // HEXEKO_SUPER_ADMIN a déjà toutes les permissions, pas besoin de les assigner

        // Appeler l'endpoint
        $response = $this->postJson("/api/v1/users/{$user->id}/resend-welcome-email");

        // Vérifier la réponse
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Welcome email successfully resent',
            ]);

        // Vérifier que l'email a été envoyé
        Mail::assertSent(WelcomeEmail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        // Vérifier que l'utilisateur a été mis à jour avec un nouveau mot de passe temporaire
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'cognito_id' => 'test-cognito-id',
        ]);

        // Vérifier que le mot de passe temporaire a été mis à jour (ne sera pas égal à l'ancien)
        $updatedUser = User::find($user->id);
        $this->assertNotEquals('old-temp-password', $updatedUser->temp_password);
    }

    #[Test]
    public function it_returns_error_if_user_does_not_exist(): void
    {
        // HEXEKO_SUPER_ADMIN a déjà toutes les permissions, pas besoin de les assigner

        // Appeler l'endpoint avec un ID inexistant
        $response = $this->postJson('/api/v1/users/non-existent-id/resend-welcome-email');

        // Vérifier que la réponse est une erreur (soit 404 soit 500 selon l'implémentation)
        // Le repository lance une ModelNotFoundException qui n'est pas gérée spécifiquement
        // dans le contrôleur, ce qui entraîne un code 500
        $response->assertStatus(500);
    }

    #[Test]
    public function it_requires_update_user_permission(): void
    {
        // Réactiver le middleware de vérification des permissions pour ce test spécifique
        $this->withMiddleware(CheckPermissionAttribute::class);

        // Créer un utilisateur de test sans donner la permission update_user
        $user = User::factory()->create();

        // Appeler l'endpoint
        $response = $this->postJson("/api/v1/users/{$user->id}/resend-welcome-email");

        // Vérifier que l'accès est refusé
        $response->assertStatus(403);
    }
}
