<?php

namespace App\Http\Controllers\V1\Apideck;

use App\Actions\Apideck\CreateVaultSessionAction;
use App\Actions\Apideck\EnsureConsumerIdAction;
use App\Exceptions\Vault\VaultException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Vault\CreateVaultSessionRequest;
use App\Jobs\Apideck\GetTotalEmployeesJob;
use App\Models\Financer;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

#[Group('Apideck')]
class VaultSessionController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        private CreateVaultSessionAction $createVaultSessionAction,
        private EnsureConsumerIdAction $ensureConsumerIdAction
    ) {}

    /**
     * Create a Vault session for SIRH integration
     *
     * Creates a secure session token for Apideck Vault that allows users to connect their SIRH accounts.
     * The session token is valid for 1 hour and provides access to the Vault UI.
     *
     * @response 200 {
     *   "data": {
     *     "session_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
     *     "expires_at": "2025-06-23T15:30:00Z",
     *     "vault_url": "https://vault.apideck.com/auth/connect/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
     *   },
     *   "meta": {
     *     "request_id": "550e8400-e29b-41d4-a716-446655440000"
     *   }
     * }
     * @response 422 {
     *   "message": "The given data was invalid.",
     *   "errors": {
     *     "financer_id": ["The financer ID is required."],
     *     "redirect_uri": ["The redirect URI must use HTTPS."]
     *   }
     * }
     * @response 403 {
     *   "message": "This action is unauthorized."
     * }
     * @response 429 {
     *   "message": "Rate limit exceeded"
     * }
     */
    public function store(CreateVaultSessionRequest $request): JsonResponse
    {
        /** @var Financer $financer */
        $financer = Financer::findOrFail($request->financer_id);

        $this->authorize('manage', $financer);

        try {
            $user = $request->user();
            if (! $user instanceof User) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // Nouvelle étape : s'assurer qu'on a un consumer_id
            $consumerId = $this->ensureConsumerIdAction->execute($financer, $user);

            $validated = $request->validated();
            $settings = is_array($validated['settings'] ?? null) ? $validated['settings'] : [];

            $redirectUri = $request->redirect_uri;
            if (! is_string($redirectUri)) {
                return response()->json(['message' => 'Invalid redirect URI'], 422);
            }
            $session = $this->createVaultSessionAction->execute(
                user: $user,
                financer: $financer,
                consumerId: $consumerId,
                redirectUri: $redirectUri,
                settings: $settings
            );

            GetTotalEmployeesJob::dispatch($financer->id, $consumerId, (string) $user->getAuthIdentifier());

            return response()->json([
                'data' => $session->toArray(),
                'meta' => ['request_id' => Str::uuid()],
            ]);
        } catch (VaultException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], $e->getCode() ?: 422);
        }
    }
}
