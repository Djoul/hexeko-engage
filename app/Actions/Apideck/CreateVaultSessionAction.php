<?php

namespace App\Actions\Apideck;

use App\DTOs\Vault\VaultSessionDTO;
use App\Events\Vault\VaultSessionCreated;
use App\Exceptions\Vault\VaultException;
use App\Models\Financer;
use App\Models\User;
use App\Services\Vault\VaultService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class CreateVaultSessionAction
{
    public function __construct(
        private VaultService $vaultService,
        private RateLimiter $rateLimiter
    ) {}

    /**
     * @param  array<string, mixed>  $settings
     *
     * @throws VaultException
     */
    public function execute(User $user, Financer $financer, string $consumerId, string $redirectUri, array $settings = []): VaultSessionDTO
    {
        Log::debug('CreateVaultSessionAction: Starting execution', [
            'user_id' => $user->id,
            'financer_id' => $financer->id,
            'consumer_id' => $consumerId,
            'redirect_uri' => $redirectUri,
            'raw_settings' => $settings,
        ]);

        if (app()->environment() !== 'local') {
            $this->enforceRateLimit($financer, $user);
        }

        $mergedSettings = $this->mergeWithDefaults($settings);

        Log::debug('CreateVaultSessionAction: Settings after merge', [
            'merged_settings' => $mergedSettings,
            'has_service_id' => array_key_exists('service_id', $mergedSettings),
        ]);

        try {
            $session = $this->vaultService->createSession($financer, $consumerId, $redirectUri, $mergedSettings);
            $this->logSuccessActivity($user, $financer, $session, $mergedSettings, $consumerId);
            $this->dispatchEvent($user, $financer, $session);

            return $session;
        } catch (VaultException $e) {
            $this->logFailureActivity($user, $financer, $e);
            throw $e;
        }
    }

    private function enforceRateLimit(Financer $financer, User $user): void
    {
        /** @var array{max_attempts: int, decay_minutes: int} $rateLimitConfig */
        $rateLimitConfig = Config::get('services.vault.rate_limit', [
            'max_attempts' => 10,
            'decay_minutes' => 60,
        ]);

        $key = "vault-session:{$financer->id}";
        $maxAttempts = (int) $rateLimitConfig['max_attempts'];
        $decayMinutes = (int) $rateLimitConfig['decay_minutes'];

        if (! $this->rateLimiter->tooManyAttempts($key, $maxAttempts)) {
            $this->rateLimiter->hit($key, $decayMinutes * 60);
        } else {
            activity()
                ->causedBy($user)
                ->event('vault_session_rate_limited')
                ->log('Too many vault session attempts');

            throw new VaultException('Rate limit exceeded', 429);
        }
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function mergeWithDefaults(array $settings): array
    {
        $defaults = [
            'unified_apis' => config('services.vault.default_unified_apis', ['hris']),
            'isolation_mode' => false,
        ];

        // Add default service_id if configured and not provided
        $defaultServiceId = config('services.vault.default_service_id');
        if (is_string($defaultServiceId) && ! array_key_exists('service_id', $settings)) {
            $defaults['service_id'] = $defaultServiceId;
        }

        $merged = array_merge($defaults, $settings);

        // Filter to only include valid Apideck Vault API settings
        // Note: service_id is included here but will be extracted and used as a header by VaultService
        $validSettings = [
            'unified_apis',
            'hide_resource_settings',
            'sandbox_mode',
            'isolation_mode',
            'session_length',
            'show_logs',
            'show_suggestions',
            'show_sidebar',
            'auto_redirect',
            'hide_guides',
            'allow_actions',
            'custom_consumer_settings',
            'service_id', // Added to pass through to VaultService
        ];

        return array_filter($merged, function ($key) use ($validSettings): bool {
            return in_array($key, $validSettings, true);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function logSuccessActivity(User $user, Financer $financer, VaultSessionDTO $session, array $settings, string $consumerId): void
    {
        activity()
            ->performedOn($financer)
            ->causedBy($user)
            ->event('vault.session.created')
            ->withProperties([
                'consumer_id' => $consumerId,
                'expires_at' => $session->expiresAt,
                'unified_apis' => $settings['unified_apis'] ?? [],
            ])
            ->log('Vault session created for SIRH integration');
    }

    private function logFailureActivity(User $user, Financer $financer, VaultException $exception): void
    {
        activity()
            ->performedOn($financer)
            ->causedBy($user)
            ->event('vault.session.failed')
            ->withProperties([
                'error' => $exception->getMessage(),
                'code' => $exception->getCode(),
            ])
            ->log('Vault session creation failed');
    }

    private function dispatchEvent(User $user, Financer $financer, VaultSessionDTO $session): void
    {
        VaultSessionCreated::dispatch($user, $financer, $session);
    }
}
