<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Team;
use App\Models\User;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Carbon\Carbon;
use Context;
use Exception;
use Illuminate\Support\Facades\Log;

class CognitoAuthService
{
    private CognitoIdentityProviderClient $client;

    public function __construct()
    {
        $timeout = config('services.cognito.timeout', 10);
        $connectTimeout = config('services.cognito.connect_timeout', 5);

        $this->client = new CognitoIdentityProviderClient([
            'region' => config('services.cognito.region'),
            'version' => 'latest',
            'http' => [
                'timeout' => $timeout,
                'connect_timeout' => $connectTimeout,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function authenticate(string $username, string $password): array
    {
        try {
            $result = $this->client->initiateAuth([
                'ClientId' => config('services.cognito.client_id'),
                'AuthFlow' => 'USER_PASSWORD_AUTH',
                'AuthParameters' => [
                    'USERNAME' => $username,
                    'PASSWORD' => $password,
                    'SECRET_HASH' => $this->calculateSecretHash($username),
                ],
            ]);

            /** @var string|null $challengeName */
            $challengeName = $result->get('ChallengeName');

            // Handle MFA challenge
            if ($challengeName === 'SMS_MFA') {
                return [
                    'success' => true,
                    'requires_mfa' => true,
                    'challenge_name' => $challengeName,
                    'session' => $result->get('Session'),
                    'challenge_parameters' => $result->get('ChallengeParameters'),
                    'destination' => $result->get('ChallengeParameters')['CODE_DELIVERY_DESTINATION'] ?? null,
                ];
            }

            /** @var array{AccessToken: string, IdToken: string, RefreshToken: string, ExpiresIn: int}|null $authResult */
            $authResult = $result->get('AuthenticationResult');

            if (! $authResult) {
                throw new Exception('Authentication result is empty');
            }

            return [
                'success' => true,
                'access_token' => $authResult['AccessToken'],
                'id_token' => $authResult['IdToken'],
                'refresh_token' => $authResult['RefreshToken'],
                'expires_in' => $authResult['ExpiresIn'],
            ];
        } catch (CognitoIdentityProviderException $e) {
            $errorCode = $e->getAwsErrorCode();

            Log::error('Cognito authentication error', [
                'error' => $e->getMessage(),
                'code' => $errorCode,
            ]);

            if ($errorCode === 'NotAuthorizedException') {
                return ['success' => false, 'error' => 'Invalid username or password'];
            }

            if ($errorCode === 'UserNotFoundException') {
                return ['success' => false, 'error' => 'User not found'];
            }

            if ($errorCode === 'UserNotConfirmedException') {
                return ['success' => false, 'error' => 'User account not confirmed'];
            }

            return ['success' => false, 'error' => 'Authentication failed'];
        } catch (Exception $e) {
            Log::error('Unexpected authentication error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'An unexpected error occurred'];
        }
    }

    public function getUserFromToken(string $accessToken): ?User
    {

        try {
            $result = $this->client->getUser([
                'AccessToken' => $accessToken,
            ]);

            $userAttributes = $result->get('UserAttributes') ?? [];
            $attributesData = is_array($userAttributes) ? $userAttributes : [];
            $attributes = collect($attributesData)
                ->pluck('Value', 'Name')
                ->toArray();

            $email = $attributes['email'] ?? null;
            $customGlobalId = $attributes['custom:global_id'] ?? null;

            $sub = $attributes['sub'] ?? null;

            if (! $email) {
                return null;
            }
            $globalTeamId = Context::get('global_team_id');
            if (! $globalTeamId) {
                $globalTeamId = Team::value('id') ?? Team::firstOrFail(['id'])->id;
                Context::add('global_team_id', $globalTeamId);
            }
            setPermissionsTeamId($globalTeamId);

            return User::with([
                'roles',
                'permissions',
                'financers',
                'financers.integrations',
                'financers.modules',
                'financers.division',
            ])
                ->where('email', $email)
                ->where('cognito_id', $customGlobalId)
                ->first();

        } catch (Exception $e) {
            Log::error('Error getting user from token', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Validate an access token
     */
    public function validateAccessToken(string $accessToken): bool
    {
        try {
            // Try to get user info with the token
            $result = $this->client->getUser([
                'AccessToken' => $accessToken,
            ]);

            return $result !== null;
        } catch (Exception $e) {
            Log::debug('Token validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Get token expiry time
     */
    public function getTokenExpiry(string $accessToken): ?Carbon
    {
        try {
            // Decode JWT to get expiry (simple base64 decode for demonstration)
            $parts = explode('.', $accessToken);
            if (count($parts) !== 3) {
                return null;
            }

            $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $parts[1])), true);
            if (! isset($payload['exp'])) {
                return null;
            }

            return Carbon::createFromTimestamp($payload['exp']);
        } catch (Exception $e) {
            Log::debug('Failed to extract token expiry', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Logout user (revoke tokens)
     */
    public function logout(string $accessToken): bool
    {
        try {
            $this->client->globalSignOut([
                'AccessToken' => $accessToken,
            ]);

            return true;
        } catch (Exception $e) {
            Log::debug('Logout failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function refreshToken(string $refreshToken): array
    {
        try {
            $result = $this->client->adminInitiateAuth([
                'UserPoolId' => config('services.cognito.user_pool_id'),
                'ClientId' => config('services.cognito.client_id'),
                'AuthFlow' => 'REFRESH_TOKEN_AUTH',
                'AuthParameters' => [
                    'REFRESH_TOKEN' => $refreshToken,
                ],
            ]);

            /** @var array{AccessToken: string, IdToken: string, ExpiresIn: int}|null $authResult */
            $authResult = $result->get('AuthenticationResult');

            if (! $authResult) {
                throw new Exception('Authentication result is empty');
            }

            return [
                'success' => true,
                'access_token' => $authResult['AccessToken'],
                'id_token' => $authResult['IdToken'],
                'expires_in' => $authResult['ExpiresIn'],
            ];
        } catch (Exception $e) {
            Log::error('Token refresh error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'Failed to refresh token'];
        }
    }

    /**
     * Verify MFA code and complete authentication
     *
     * @return array<string, mixed>
     */
    public function verifyMfaCode(string $username, string $mfaCode, string $session): array
    {
        try {
            $result = $this->client->respondToAuthChallenge([
                'ClientId' => config('services.cognito.client_id'),
                'ChallengeName' => 'SMS_MFA',
                'Session' => $session,
                'ChallengeResponses' => [
                    'USERNAME' => $username,
                    'SMS_MFA_CODE' => $mfaCode,
                    'SECRET_HASH' => $this->calculateSecretHash($username),
                ],
            ]);

            /** @var array{AccessToken: string, IdToken: string, RefreshToken: string, ExpiresIn: int}|null $authResult */
            $authResult = $result->get('AuthenticationResult');

            if (! $authResult) {
                throw new Exception('Authentication result is empty after MFA verification');
            }

            return [
                'success' => true,
                'access_token' => $authResult['AccessToken'],
                'id_token' => $authResult['IdToken'],
                'refresh_token' => $authResult['RefreshToken'],
                'expires_in' => $authResult['ExpiresIn'],
            ];
        } catch (CognitoIdentityProviderException $e) {
            $errorCode = $e->getAwsErrorCode();

            Log::error('MFA verification error', [
                'error' => $e->getMessage(),
                'code' => $errorCode,
            ]);

            if ($errorCode === 'CodeMismatchException') {
                return ['success' => false, 'error' => 'Invalid verification code'];
            }

            if ($errorCode === 'ExpiredCodeException') {
                return ['success' => false, 'error' => 'Verification code has expired'];
            }

            if ($errorCode === 'NotAuthorizedException') {
                return ['success' => false, 'error' => 'Not authorized to verify code'];
            }

            return ['success' => false, 'error' => 'MFA verification failed'];
        } catch (Exception $e) {
            Log::error('Unexpected MFA verification error', ['error' => $e->getMessage()]);

            return ['success' => false, 'error' => 'An unexpected error occurred during MFA verification'];
        }
    }

    private function calculateSecretHash(string $username): string
    {
        $clientIdConfig = config('services.cognito.client_id');
        $clientSecretConfig = config('services.cognito.client_secret');

        $clientId = is_string($clientIdConfig) ? $clientIdConfig : '';
        $clientSecret = is_string($clientSecretConfig) ? $clientSecretConfig : '';

        $message = $username.$clientId;

        return base64_encode(hash_hmac('sha256', $message, $clientSecret, true));
    }
}
