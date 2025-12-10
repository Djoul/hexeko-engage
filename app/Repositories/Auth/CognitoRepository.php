<?php

namespace App\Repositories\Auth;

use App\Models\User;
use App\Repositories\Contracts\AuthRepositoryInterface;
use App\Traits\CognitoConfigTrait;
use Aws\Exception\AwsException;
use Exception;
use Log;

/*
 * will be removed in the future the auth wil be managed by front
 * @deprecated
 * */

class CognitoRepository implements AuthRepositoryInterface
{
    use CognitoConfigTrait;

    /**
     * @param  array{email: string, password: string}  $credentials
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function login(array $credentials): array
    {
        try {
            $secretHash = base64_encode(
                hash_hmac('sha256', $credentials['email'].$this->config['client_id'], $this->config['client_secret'],
                    true)
            );

            /*      $result = $this->client->initiateAuth([
                      'AuthFlow' => 'USER_PASSWORD_AUTH',
                      'ClientId' => $this->config['client_id'],
                      'AuthParameters' => [
                          'USERNAME' => $credentials['email'],
                          'PASSWORD' => $credentials['password'],
                          'SECRET_HASH' => $secretHash,
                      ],
                  ]);*/
            Log::debug('login', [
                'email' => $credentials['email'],
                'password' => $credentials['password'],
                'client_id' => $this->config['client_id'],
                'client_secret' => $this->config['client_secret'],
                'secret_hash' => $secretHash,
            ]);

            $result = $this->client->adminInitiateAuth([
                'AuthFlow' => 'ADMIN_USER_PASSWORD_AUTH',
                'ClientId' => $this->config['client_id'],
                'UserPoolId' => $this->config['user_pool_id'],
                'AuthParameters' => [
                    'USERNAME' => $credentials['email'],
                    'PASSWORD' => $credentials['password'],
                    'SECRET_HASH' => $secretHash,
                ],
            ]);

            return $result->toArray();

        } catch (AwsException $e) {
            $message = $e->getAwsErrorMessage() ?? 'Unknown AWS error';

            Log::error($message, ['exception' => $e]);
            throw new Exception($message, $e->getCode(), $e);
        }
    }

    /**
     * @throws Exception
     */
    public function logout(string $accessToken): bool
    {
        try {
            $this->client->globalSignOut([
                'AccessToken' => $accessToken,
            ]);

            return true;

        } catch (AwsException $e) {
            throw new Exception($e->getAwsErrorMessage() ?? 'Unknown AWS error', $e->getCode(), $e);
        }
    }

    /**
     * @return array
     *               todo register
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function register(User $user): array
    {

        $resp = $this->client->adminCreateUser([
            'UserPoolId' => config('services.cognito.user_pool_id'),
            'Username' => $user->email,
            'TemporaryPassword' => $user->temp_password,
            'UserAttributes' => [
                ['Name' => 'email', 'Value' => $user->email],
                ['Name' => 'name', 'Value' => $user->first_name],
                ['Name' => 'given_name', 'Value' => $user->last_name],
            ],
        ]);

        return $resp->toArray();
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur dans Cognito
     *
     * @param  User  $user  L'utilisateur dont le mot de passe doit être réinitialisé
     * @param  string  $tempPassword  Le nouveau mot de passe temporaire
     * @return array<string, mixed>
     *
     * @throws Exception
     */
    public function resetPassword(User $user, string $tempPassword): array
    {
        try {
            // Vérifier que l'utilisateur a un cognito_id
            if (empty($user->cognito_id)) {
                throw new Exception("L'utilisateur n'a pas d'identifiant Cognito");
            }

            // Réinitialiser le mot de passe de l'utilisateur
            $resp = $this->client->adminSetUserPassword([
                'UserPoolId' => $this->config['user_pool_id'],
                'Username' => $user->email,
                'Password' => $tempPassword,
                'Permanent' => false, // Mot de passe temporaire
            ]);

            return $resp->toArray();
        } catch (AwsException $e) {
            $message = $e->getAwsErrorMessage() ?? 'Erreur AWS inconnue';
            Log::error($message, ['exception' => $e]);
            throw new Exception($message, $e->getCode(), $e);
        }
    }
}
