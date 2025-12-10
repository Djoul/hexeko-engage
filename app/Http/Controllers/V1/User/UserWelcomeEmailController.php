<?php

declare(strict_types=1);

namespace App\Http\Controllers\V1\User;

use App\Attributes\RequiresPermission;
use App\Enums\IDP\PermissionDefaults;
use App\Http\Controllers\Controller;
use App\Mail\WelcomeEmail;
use App\Services\CognitoService;
use App\Services\Models\UserService;
use Dedoc\Scramble\Attributes\Group;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class UserWelcomeEmailController
 */
#[Group('User')]
class UserWelcomeEmailController extends Controller
{
    /**
     * UserWelcomeEmailController constructor.
     */
    public function __construct(
        protected UserService $userService,
        protected CognitoService $cognitoService
    ) {}

    /**
     * Send welcome email
     *
     * Send a welcome email to a newly created user.
     */
    #[RequiresPermission(PermissionDefaults::UPDATE_USER)]
    public function resendWelcomeEmail(string $id): JsonResponse
    {
        // Trouver l'utilisateur
        $user = $this->userService->find($id);

        // Générer un nouveau mot de passe temporaire
        $tempPassword = generateSecurePassword(10);

        try {
            // Mettre à jour l'utilisateur dans Cognito avec le nouveau mot de passe
            $this->cognitoService->resetPassword($user, $tempPassword);

            // Mettre à jour le mot de passe temporaire dans la base de données
            $this->userService->update($user, [
                'temp_password' => $tempPassword,
            ]);

            // Envoyer l'email de bienvenue
            Mail::to($user->email)->send(new WelcomeEmail($user));

            return response()->json([
                'message' => 'Welcome email successfully resent',
            ])->setStatusCode(Response::HTTP_OK);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'envoi de l\'email de bienvenue',
                'error' => $e->getMessage(),
            ])->setStatusCode(Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
