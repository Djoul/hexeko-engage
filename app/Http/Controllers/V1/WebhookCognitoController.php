<?php

namespace App\Http\Controllers\V1;

use App\Enums\IDP\RoleDefaults;
use App\Enums\Languages;
use App\Events\Metrics\UserAccountActivated;
use App\Http\Controllers\Controller;
use App\Models\Financer;
use App\Models\Team;
use App\Models\User;
use App\Services\Models\UserService;
use Artisan;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Log;
use Symfony\Component\HttpFoundation\Response;

class WebhookCognitoController extends Controller
{
    public function __construct(
        protected UserService $userService
    ) {}

    /**
     * Handle the webhook from AWS Cognito
     */
    public function handle(Request $request): JsonResponse
    {

        try {
            //            Log::info('Cognito post-signup webhook received', ['payload' => $request->all()]);
            $payload = $request->all();
            if (! array_key_exists('sub', $payload) || ! array_key_exists('email', $payload)) {
                return response()->json([
                    'message' => 'Invalid webhook payload',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find pending invitation by ID or email
            $invitedUser = User::where('id', $payload['invited_user_id'])
                ->where('invitation_status', 'pending')
                ->first();

            // Fallback: try finding by email if ID not found
            if (! $invitedUser) {
                $invitedUser = User::where('email', $payload['email'])
                    ->where('invitation_status', 'pending')
                    ->first();
            }

            if (! $invitedUser) {
                return response()->json([
                    'message' => 'Pending invitation not found for this user',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get financer from invitation_metadata or from user's financers
            /** @var array<string, mixed>|null $invitationMeta */
            $invitationMeta = $invitedUser->invitation_metadata;
            $financerIdRaw = is_array($invitationMeta) ? ($invitationMeta['financer_id'] ?? null) : null;

            if (! $financerIdRaw && $invitedUser->financers->isNotEmpty()) {
                $financerIdRaw = $invitedUser->financers->first()->id;
            }

            $financerId = is_string($financerIdRaw) ? $financerIdRaw : null;

            $financer = in_array($financerId, [null, '', '0'], true) ? null : Financer::where('id', $financerId)->first();

            if (! $financer) {
                return response()->json([
                    'message' => 'Associated financer not found',
                ], Response::HTTP_NOT_FOUND);
            }

            // Get the first team or create one if none exists
            $team = Team::first();

            if (! $team) {
                return response()->json([
                    'message' => 'No team available',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Check if language is provided in payload (supports both 'reg_language' and 'custom:reg_language')
            $customLanguage = $payload['reg_language'] ?? $payload['custom:reg_language'] ?? null;

            // Use requested language if provided and supported by financer, otherwise use financer's first language
            if ($customLanguage && in_array($customLanguage, $financer->available_languages, true)) {
                $defaultLocale = $customLanguage;
            } elseif (! empty($financer->available_languages) && is_array($financer->available_languages)) {
                // Use first available language from financer, default to ENGLISH if empty
                $defaultLocale = $financer->available_languages[0];
            } else {
                $defaultLocale = Languages::ENGLISH;
            }

            // Update the existing invited user to complete their registration
            $invitedUser->update([
                'cognito_id' => $payload['sub'],
                'phone' => $payload['phone_number'] ?? null,
                'locale' => $defaultLocale,
                'enabled' => true,
                'team_id' => $invitedUser->team_id ?? $team->id,
                'invitation_status' => 'accepted',
                'invitation_accepted_at' => now(),
            ]);

            $user = $invitedUser;

            // Get the intended role from invitation metadata (single role system)
            $role = $invitedUser->invitation_metadata['intended_role'] ?? RoleDefaults::BENEFICIARY;

            setPermissionsTeamId($user->team_id);
            $user->assignRole($role);

            // Get additional data from invitation_metadata
            /** @var array<string, mixed>|null $invitationMetadata */
            $invitationMetadata = $invitedUser->invitation_metadata;
            $externalIdRaw = is_array($invitationMetadata) ? ($invitationMetadata['external_id'] ?? '') : '';
            $sirhIdRaw = is_array($invitationMetadata) ? ($invitationMetadata['sirh_id'] ?? '') : '';

            $externalId = is_string($externalIdRaw) ? $externalIdRaw : '';
            $sirhId = is_string($sirhIdRaw) ? $sirhIdRaw : '';

            // Update or attach the financer with active status (single role system)
            /** @var array<int, array{id: string, pivot?: array{active?: bool, from?: string, to?: string, sirh_id?: string, role?: string, language?: string}}> $financerData */
            $financerData = [
                [
                    'id' => (string) $financerId,
                    'pivot' => [
                        'active' => true,
                        'from' => $invitedUser->created_at ? $invitedUser->created_at->toDateString() : now()->toDateString(),
                        'sirh_id' => $sirhId,
                        'role' => $role,
                        'language' => $defaultLocale,
                    ],
                ],
            ];

            $this->userService->syncFinancers($user, $financerData);

            Artisan::call('cache:clear');
            Artisan::call('permission:cache-reset');
            // Log the event for metrics
            event(new UserAccountActivated((string) $user->id));

            return response()->json([
                'message' => 'User created successfully',
                'data' => $user,
            ], Response::HTTP_OK);
        } catch (Exception $e) {

            Log::error('Error handling Cognito webhook: '.$e->getMessage(), [
                'trace' => $e->getTrace(),
            ]);

            return response()->json([
                'message' => 'Error handling webhook',
                'error' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
