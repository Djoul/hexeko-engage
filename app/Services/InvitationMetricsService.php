<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

/**
 * Service for tracking and reporting invitation metrics.
 * Sprint 1 - Foundation: Provides real-time metrics for invitation lifecycle.
 */
class InvitationMetricsService
{
    /**
     * Get count of pending invitations.
     */
    public function getPendingInvitationsCount(): int
    {
        return User::invited()->count();
    }

    /**
     * Get count of accepted invitations.
     */
    public function getAcceptedInvitationsCount(): int
    {
        return User::where('invitation_status', 'accepted')->count();
    }

    /**
     * Get count of expired invitations.
     */
    public function getExpiredInvitationsCount(): int
    {
        return User::expiredInvitations()->count();
    }

    /**
     * Get count of revoked invitations.
     */
    public function getRevokedInvitationsCount(): int
    {
        return User::where('invitation_status', 'revoked')->count();
    }

    /**
     * Get all invitation metrics in a single call.
     *
     * @return array{pending: int, accepted: int, expired: int, revoked: int, total: int}
     */
    public function getAllMetrics(): array
    {
        $pending = $this->getPendingInvitationsCount();
        $accepted = $this->getAcceptedInvitationsCount();
        $expired = $this->getExpiredInvitationsCount();
        $revoked = $this->getRevokedInvitationsCount();

        return [
            'pending' => $pending,
            'accepted' => $accepted,
            'expired' => $expired,
            'revoked' => $revoked,
            'total' => $pending + $accepted + $expired + $revoked,
        ];
    }

    /**
     * Get invitation count for a specific inviter.
     */
    public function getInvitationCountByInviter(string $inviterId): int
    {
        return User::invitedBy($inviterId)->count();
    }

    /**
     * Calculate acceptance rate as a percentage.
     * Formula: (accepted / (accepted + pending + expired + revoked)) * 100
     */
    public function getAcceptanceRate(): float
    {
        $metrics = $this->getAllMetrics();

        if ($metrics['total'] === 0) {
            return 0.0;
        }

        return round(($metrics['accepted'] / $metrics['total']) * 100, 2);
    }

    /**
     * Get invitations that will expire soon (within specified days).
     *
     * @param  int  $days  Number of days to look ahead (default: 3)
     * @return Collection<int, User>
     */
    public function getInvitationsExpiringSoon(int $days = 3): Collection
    {
        $now = now();
        $futureDate = now()->addDays($days);

        return User::where('invitation_status', 'pending')
            ->whereBetween('invitation_expires_at', [$now, $futureDate])
            ->get();
    }

    /**
     * Get comprehensive metrics summary with metadata.
     *
     * @return array{metrics: array, acceptance_rate: float, timestamp: Carbon}
     */
    public function getSummary(): array
    {
        return [
            'metrics' => $this->getAllMetrics(),
            'acceptance_rate' => $this->getAcceptanceRate(),
            'timestamp' => Carbon::now(),
        ];
    }
}
