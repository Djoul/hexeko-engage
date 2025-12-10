<?php

declare(strict_types=1);

namespace App\Integrations\Wellbeing\WellWo\Services;

use App\Integrations\Wellbeing\WellWo\Exceptions\WellWoAuthException;

class WellWoAuthService
{
    /**
     * Get authentication token for WellWo API
     *
     * @return array<string, mixed>
     *
     * @throws WellWoAuthException
     */
    public function getAuthToken(): array
    {
        // Stub implementation
        return ['token' => 'stub-token'];
    }

    /**
     * Get authentication headers
     *
     * @return array<string, string>
     */
    public function getAuthHeaders(): array
    {
        // Stub implementation
        return ['Authorization' => 'Bearer stub-token'];
    }

    /**
     * Check if token is valid
     */
    public function isTokenValid(string $token): bool
    {
        // Stub implementation
        return ! empty($token);
    }
}
