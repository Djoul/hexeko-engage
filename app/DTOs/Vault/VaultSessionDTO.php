<?php

namespace App\DTOs\Vault;

class VaultSessionDTO
{
    public string $sessionToken;

    public string $expiresAt;

    public string $vaultUrl;

    /** @var array<string, mixed>|null */
    public ?array $consumerMetadata;

    /** @var array<string, mixed>|null */
    public ?array $settings;

    public ?string $createdAt;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data)
    {
        $this->sessionToken = is_string($data['session_token'] ?? null) ? $data['session_token'] : '';
        $this->expiresAt = is_string($data['expires_at'] ?? null) ? $data['expires_at'] : now()->addMinutes(60)->toIso8601String();
        $this->vaultUrl = $this->buildVaultUrl($this->sessionToken);
        $consumerMeta = $data['consumer_metadata'] ?? null;
        /** @var array<string, mixed>|null $consumerMeta */
        $this->consumerMetadata = is_array($consumerMeta) ? $consumerMeta : null;

        $settingsData = $data['settings'] ?? null;
        /** @var array<string, mixed>|null $settingsData */
        $this->settings = is_array($settingsData) ? $settingsData : null;
        $this->createdAt = is_string($data['created_at'] ?? null) ? $data['created_at'] : null;
    }

    private function buildVaultUrl(string $sessionToken): string
    {
        return "https://vault.apideck.com/auth/connect/{$sessionToken}";
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'session_token' => $this->sessionToken,
            'expires_at' => $this->expiresAt,
            'vault_url' => $this->vaultUrl,
            'consumer_metadata' => $this->consumerMetadata,
            'settings' => $this->settings,
            'created_at' => $this->createdAt,
        ];
    }
}
