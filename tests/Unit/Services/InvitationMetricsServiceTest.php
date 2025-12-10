<?php

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\InvitationMetricsService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TDD Tests for InvitationMetricsService.
 * Sprint 1 - Foundation: Validates invitation metrics tracking and reporting.
 */
#[Group('user')]
#[Group('invited-user')]
class InvitationMetricsServiceTest extends TestCase
{
    use DatabaseTransactions;

    private InvitationMetricsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new InvitationMetricsService;
    }

    #[Test]
    public function it_counts_total_pending_invitations(): void
    {
        // Arrange
        $initialCount = User::invited()->count();
        User::factory()->invited()->count(3)->create();
        User::factory()->invitedAccepted()->create(); // Should NOT be counted

        // Act
        $count = $this->service->getPendingInvitationsCount();

        // Assert
        $this->assertEquals($initialCount + 3, $count);
    }

    #[Test]
    public function it_counts_total_accepted_invitations(): void
    {
        // Arrange
        $initialCount = User::where('invitation_status', 'accepted')->count();
        User::factory()->invitedAccepted()->count(2)->create();
        User::factory()->invited()->create(); // Should NOT be counted

        // Act
        $count = $this->service->getAcceptedInvitationsCount();

        // Assert
        $this->assertEquals($initialCount + 2, $count);
    }

    #[Test]
    public function it_counts_total_expired_invitations(): void
    {
        // Arrange
        $initialCount = User::expiredInvitations()->count();
        User::factory()->invitedExpired()->count(2)->create();
        User::factory()->invited()->create(); // Should NOT be counted (not expired)

        // Act
        $count = $this->service->getExpiredInvitationsCount();

        // Assert
        $this->assertEquals($initialCount + 2, $count);
    }

    #[Test]
    public function it_counts_total_revoked_invitations(): void
    {
        // Arrange
        $initialCount = User::where('invitation_status', 'revoked')->count();
        User::factory()->invitedRevoked()->count(2)->create();
        User::factory()->invited()->create(); // Should NOT be counted

        // Act
        $count = $this->service->getRevokedInvitationsCount();

        // Assert
        $this->assertEquals($initialCount + 2, $count);
    }

    #[Test]
    public function it_returns_all_invitation_metrics(): void
    {
        // Arrange
        User::factory()->invited()->count(5)->create();
        User::factory()->invitedAccepted()->count(3)->create();
        User::factory()->invitedExpired()->count(2)->create();
        User::factory()->invitedRevoked()->count(1)->create();

        // Act
        $metrics = $this->service->getAllMetrics();

        // Assert
        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('pending', $metrics);
        $this->assertArrayHasKey('accepted', $metrics);
        $this->assertArrayHasKey('expired', $metrics);
        $this->assertArrayHasKey('revoked', $metrics);
        $this->assertArrayHasKey('total', $metrics);

        // Verify total is sum of all statuses
        $expectedTotal = $metrics['pending'] + $metrics['accepted'] + $metrics['expired'] + $metrics['revoked'];
        $this->assertEquals($expectedTotal, $metrics['total']);
    }

    #[Test]
    public function it_counts_invitations_by_specific_inviter(): void
    {
        // Arrange
        $inviter1 = User::factory()->create();
        $inviter2 = User::factory()->create();

        User::factory()->invited($inviter1)->count(3)->create();
        User::factory()->invited($inviter2)->count(2)->create();

        // Act
        $count = $this->service->getInvitationCountByInviter($inviter1->id);

        // Assert
        $this->assertEquals(3, $count);
    }

    #[Test]
    public function it_calculates_acceptance_rate(): void
    {
        // Arrange - Create clean state for rate calculation
        User::factory()->invited()->count(5)->create();
        User::factory()->invitedAccepted()->count(3)->create();
        User::factory()->invitedExpired()->count(2)->create();

        // Act
        $rate = $this->service->getAcceptanceRate();

        // Assert
        $this->assertIsFloat($rate);
        $this->assertGreaterThanOrEqual(0.0, $rate);
        $this->assertLessThanOrEqual(100.0, $rate);
    }

    #[Test]
    public function it_returns_zero_acceptance_rate_when_no_invitations(): void
    {
        // Arrange - Ensure no invitations with any status
        // Using transactions, so any previous test data is rolled back

        // Act
        $rate = $this->service->getAcceptanceRate();

        // Assert
        $this->assertEquals(0.0, $rate);
    }

    #[Test]
    public function it_gets_invitations_expiring_soon(): void
    {
        // Arrange
        // Create invitation expiring in 1 day (should be included)
        $expiringSoon = User::factory()->invited()->create([
            'invitation_expires_at' => now()->addDay(),
        ]);

        // Create invitation expiring in 10 days (should NOT be included)
        User::factory()->invited()->create([
            'invitation_expires_at' => now()->addDays(10),
        ]);

        // Create already expired invitation (should NOT be included)
        User::factory()->invitedExpired()->create();

        // Act - Get invitations expiring in next 2 days
        $expiringInvitations = $this->service->getInvitationsExpiringSoon(2);

        // Assert
        $this->assertCount(1, $expiringInvitations);
        $this->assertTrue($expiringInvitations->contains($expiringSoon));
    }

    #[Test]
    public function it_returns_metrics_summary_as_array(): void
    {
        // Arrange
        User::factory()->invited()->count(2)->create();
        User::factory()->invitedAccepted()->count(3)->create();

        // Act
        $summary = $this->service->getSummary();

        // Assert
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('metrics', $summary);
        $this->assertArrayHasKey('acceptance_rate', $summary);
        $this->assertArrayHasKey('timestamp', $summary);

        $this->assertIsArray($summary['metrics']);
        $this->assertIsFloat($summary['acceptance_rate']);
        $this->assertInstanceOf(DateTimeInterface::class, $summary['timestamp']);
    }
}
