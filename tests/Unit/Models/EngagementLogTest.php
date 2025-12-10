<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\EngagementLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Contracts\Auditable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('engagement')]
#[Group('audit')]
class EngagementLogTest extends TestCase
{
    #[Test]
    public function it_uses_uuid_as_primary_key(): void
    {
        $engagementLog = new EngagementLog;

        $this->assertTrue($engagementLog->getIncrementing() === false);
        $this->assertEquals('string', $engagementLog->getKeyType());
    }

    #[Test]
    public function it_has_correct_table_name(): void
    {
        $engagementLog = new EngagementLog;

        $this->assertEquals('engagement_logs', $engagementLog->getTable());
    }

    #[Test]
    public function it_implements_auditable_interface(): void
    {
        $engagementLog = new EngagementLog;

        $this->assertInstanceOf(Auditable::class, $engagementLog);
    }

    #[Test]
    public function it_has_correct_casts(): void
    {
        $engagementLog = new EngagementLog;
        $casts = $engagementLog->getCasts();

        $this->assertArrayHasKey('metadata', $casts);
        $this->assertEquals('array', $casts['metadata']);
        $this->assertArrayHasKey('logged_at', $casts);
        $this->assertEquals('datetime', $casts['logged_at']);
    }

    #[Test]
    public function it_belongs_to_user(): void
    {
        $engagementLog = new EngagementLog;
        $relation = $engagementLog->user();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertEquals(User::class, $relation->getRelated()::class);
    }

    #[Test]
    public function it_can_create_engagement_log(): void
    {
        $user = User::factory()->create();

        $engagementLog = EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'login',
            'metadata' => ['ip' => '127.0.0.1', 'browser' => 'Chrome'],
            'logged_at' => now(),
        ]);

        $this->assertDatabaseHas('engagement_logs', [
            'user_id' => $user->id,
            'type' => 'login',
        ]);

        $this->assertIsArray($engagementLog->metadata);
        $this->assertEquals('127.0.0.1', $engagementLog->metadata['ip']);
        $this->assertEquals('Chrome', $engagementLog->metadata['browser']);
    }

    #[Test]
    public function it_casts_logged_at_to_datetime(): void
    {
        $user = User::factory()->create();
        $loggedAt = now();

        $engagementLog = EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'page_view',
            'logged_at' => $loggedAt,
        ]);

        $this->assertInstanceOf(Carbon::class, $engagementLog->logged_at);
        $this->assertEquals($loggedAt->format('Y-m-d H:i:s'), $engagementLog->logged_at->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_can_store_complex_metadata(): void
    {
        $user = User::factory()->create();
        $metadata = [
            'device' => [
                'type' => 'mobile',
                'os' => 'iOS',
                'version' => '15.0',
            ],
            'location' => [
                'country' => 'France',
                'city' => 'Paris',
            ],
            'session_duration' => 3600,
        ];

        $engagementLog = EngagementLog::create([
            'user_id' => $user->id,
            'type' => 'session_end',
            'metadata' => $metadata,
            'logged_at' => now(),
        ]);

        $this->assertEquals($metadata, $engagementLog->metadata);
        $this->assertEquals('mobile', $engagementLog->metadata['device']['type']);
        $this->assertEquals('France', $engagementLog->metadata['location']['country']);
        $this->assertEquals(3600, $engagementLog->metadata['session_duration']);
    }
}
