<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Test routes pour l'authentification broadcasting
Route::prefix('test-broadcasting')->group(function (): void {

    // Route pour générer un token JWT de test
    Route::post('/generate-token', function (Request $request) {
        $userId = $request->input('user_id');

        // Si pas d'ID fourni, prendre le premier utilisateur
        $user = $userId ? User::find($userId) : User::first();

        if (! $user) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'Please provide a valid user_id or ensure users exist',
            ], 404);
        }

        // Générer un token JWT simple pour les tests
        // Note: En production, utilisez AWS Cognito ou un service JWT approprié
        $payload = [
            'sub' => $user->id,
            'email' => $user->email,
            'name' => $user->first_name.' '.$user->last_name,
            'iat' => time(),
            'exp' => time() + 3600, // 1 heure
        ];

        // Clé secrète pour les tests uniquement
        $secret = env('APP_KEY', 'test-secret-key');

        // Encoder le token (simplifié pour les tests)
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $payload = json_encode($payload);

        $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));

        $signature = hash_hmac('sha256', $base64Header.'.'.$base64Payload, $secret, true);
        $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));

        $jwt = $base64Header.'.'.$base64Payload.'.'.$base64Signature;

        return response()->json([
            'success' => true,
            'token' => $jwt,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
            ],
        ]);
    });

    // Route pour récupérer le premier utilisateur disponible
    Route::get('/first-user', function () {
        $user = User::first();

        if (! $user) {
            return response()->json([
                'error' => 'No users found',
                'message' => 'Please create a user first',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
            ],
        ]);
    });

    // Route pour créer une session de test
    Route::post('/login', function (Request $request) {
        $userId = $request->input('user_id');

        if (! $userId) {
            return response()->json([
                'error' => 'User ID required',
                'message' => 'Please provide a user_id (UUID format)',
            ], 400);
        }

        // Chercher l'utilisateur par UUID (primary key)
        $user = User::find($userId);

        if (! $user) {
            return response()->json([
                'error' => 'User not found',
                'message' => 'Please provide a valid user UUID',
            ], 404);
        }

        // Forcer la connexion de l'utilisateur
        Auth::login($user);

        // Créer aussi un cookie pour faciliter l'authentification
        $response = response()->json([
            'success' => true,
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->first_name.' '.$user->last_name,
            ],
            'session_id' => session()->getId(),
            'csrf_token' => csrf_token(),
        ]);

        // Ajouter un cookie de test pour l'authentification
        return $response->cookie('test_user_id', $user->id, 60);
    });

    // Route pour vérifier l'état de la session
    Route::get('/check-auth', function () {
        if (Auth::check()) {
            $user = Auth::user();

            return response()->json([
                'authenticated' => true,
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->first_name.' '.$user->last_name,
                ],
            ]);
        }

        return response()->json([
            'authenticated' => false,
        ]);
    });

    // Route pour se déconnecter
    Route::post('/logout', function () {
        Auth::logout();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully',
        ]);
    });

    // Route alternative pour l'authentification des canaux privés en test
    Route::post('/auth', function (Request $request) {
        // Récupérer le nom du canal
        $channelName = $request->input('channel_name');
        $socketId = $request->input('socket_id');

        // Vérifier si l'utilisateur est connecté
        if (! Auth::check()) {
            return response()->json(['error' => 'Not authenticated'], 403);
        }

        $user = Auth::user();

        // Pour les tests, autoriser tous les canaux privés pour l'utilisateur connecté
        if (str_starts_with($channelName, 'private-')) {
            // Extraire le type et l'ID du canal
            // Format attendu : private-user.{id} ou private-financer.{id} etc.
            if (preg_match('/private-user\.(.+)/', $channelName, $matches)) {
                $requestedUserId = $matches[1];
                // Vérifier que l'utilisateur demande son propre canal
                if ($user->id !== $requestedUserId) {
                    return response()->json(['error' => 'Unauthorized channel'], 403);
                }
            }

            // Créer la signature pour Pusher/Reverb
            $stringToSign = $socketId.':'.$channelName;
            $signature = hash_hmac('sha256', $stringToSign, env('REVERB_APP_SECRET'));

            return response()->json([
                'auth' => env('REVERB_APP_KEY').':'.$signature,
            ]);
        }

        return response()->json(['error' => 'Invalid channel'], 403);
    })->middleware('web');
});
