<?php

namespace App\Http\Middleware;

use App;
use App\Events\UserAuthenticated;
use App\Models\Team;
use App\Models\User;
use Aws\CognitoIdentityProvider\CognitoIdentityProviderClient;
use Aws\CognitoIdentityProvider\Exception\CognitoIdentityProviderException;
use Aws\Exception\AwsException;
use Closure;
use Context;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use PDOException;
use Symfony\Component\HttpFoundation\Response;

class CognitoAuthMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            $token = $request->bearerToken();

            if (! $token) {
                return response()->json(['error' => 'Authorization token is missing'], 401);
            }

            // AWS Cognito configuration
            try {
                $client = new CognitoIdentityProviderClient([
                    'region' => config('services.cognito.region'),
                    'version' => 'latest',
                    'credentials' => [
                        'key' => config('services.cognito.client_id'),
                        'secret' => config('services.cognito.client_secret'),
                    ],
                ]);

            } catch (Exception $e) {
                Log::error('Cognito client initialization error', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Authentication service configuration error'], 500);
            }

            // Retrieve user information via Cognito
            try {
                $result = $client->getUser([
                    'AccessToken' => $token,
                ]);
            } catch (CognitoIdentityProviderException $e) {
                $errorCode = $e->getAwsErrorCode();

                Log::error('Cognito getUser error', [
                    'error' => $e->getMessage(),
                    'code' => $errorCode,
                ]);

                if ($errorCode === 'NotAuthorizedException') {
                    return response()->json(['error' => 'Token expired or invalid'], 401);
                }
                if ($errorCode === 'InvalidParameterException') {
                    return response()->json(['error' => 'Invalid token format'], 400);
                }

                if ($errorCode === 'ResourceNotFoundException') {
                    return response()->json(['error' => 'User not found in authentication service'], 404);
                }

                Log::error('Cognito getUser error', [
                    'error' => $e->getMessage(),
                    'code' => $errorCode,
                ]);

                return response()->json(['error' => 'Authentication service error: '.$errorCode], 401);
            } catch (AwsException $e) {
                Log::error('AWS service error', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'AWS service error'], 500);
            }

            // Extract user attributes
            try {
                $userAttributes = $result->get('UserAttributes');
                $attributes = collect(is_array($userAttributes) ? $userAttributes : [])
                    ->pluck('Value', 'Name')
                    ->toArray();

                $cognitoId = $attributes['custom:global_id'] ?? $attributes['sub'] ?? null;
                $email = $attributes['email'] ?? null;

                if (! $email || ! $cognitoId) {
                    return response()->json(['error' => 'Required user information missing from token'], 401);
                }
            } catch (Exception $e) {
                Log::error('Error parsing user attributes', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Error processing user information'], 500);
            }

            // Get team and set permissions
            try {
                $team = Team::firstOrFail();
                setPermissionsTeamId($team->id);
            } catch (Exception $e) {

                Log::error('Team retrieval error', ['error' => $e->getMessage()]);

                return response()->json(['error' => $e->getMessage()], 500);
            }

            // Check if the user exists in the database
            try {
                $user = User::with([
                    'roles',
                    'permissions',
                    'financers',
                    'financers.integrations',
                    'financers.modules',
                    'financers.division',
                ])
                    ->where('cognito_id', $cognitoId)
                    ->where('email', $email)
                    ->first();

                if (! $user) {
                    return response()->json([
                        'error' => 'User authenticated but not found in application database',
                        'cognito_id' => $cognitoId,
                        'email' => $email,
                    ], 401);
                }

                try {
                    // Initialize authorization context from user and request
                    authorizationContext()->hydrateFromRequest($user);
                } catch (AuthorizationException $exception) {
                    Log::warning('Authorization scope violation detected during authentication', [
                        'error' => $exception->getMessage(),
                        'user_id' => $user->id,
                        'requested_financer_id' => request()->query('financer_id'),
                    ]);

                    return response()->json(['error' => $exception->getMessage()], 403);
                }

                // Maintain backward compatibility with legacy Context
                Context::add('accessible_divisions', authorizationContext()->divisionIds());
                Context::add('accessible_financers', authorizationContext()->financerIds());

            } catch (PDOException $e) {
                Log::error('Database connection error in CognitoAuthMiddleware', [
                    'error' => $e->getMessage(),
                    'code' => $e->getCode(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString(),
                    'cognito_id' => $cognitoId,
                    'email' => $email,
                ]);

                return response()->json(['error' => 'Database connection error'], 500);
            } catch (ModelNotFoundException $e) {
                Log::error('User model not found', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'User not found'], 404);
            } catch (Exception $e) {
                Log::error('Database user retrieval error', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Error retrieving user data'], 500);
            }

            // Authenticate the user
            try {
                Auth::login($user);

                // Override with user locale if available
                if (! empty($user->locale)) {
                    $locale = $user->locale;
                    App::setLocale($locale);
                }

                // Log successful authentication with user context
                // This happens AFTER Auth::login() so auth()->id() is available
                //                Log::info('User authenticated successfully', [
                //                    'event' => 'auth.success',
                //                    'user' => [
                //                        'id' => $user->id,
                //                        'email' => $user->email,
                //                        'cognito_id' => $user->cognito_id,
                //                    ],
                //                    'roles' => $user->roles->pluck('name')->toArray(),
                //                    'timestamp' => now()->toIso8601String(),
                //                ]);

                // Dispatch user authenticated event for monitoring
                event(new UserAuthenticated($user));

            } catch (Exception $e) {
                Log::error('Auth login error', ['error' => $e->getMessage()]);

                return response()->json(['error' => 'Error during authentication process'], 500);
            }

            /** @var Response */
            return $next($request);

        } catch (Exception $e) {
            // Catch-all for any unexpected errors
            Log::error('Unexpected authentication middleware error', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'Authentication process failed'], 500);
        }
    }
}
