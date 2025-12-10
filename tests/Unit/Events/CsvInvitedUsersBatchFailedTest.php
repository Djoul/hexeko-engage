<?php

namespace Tests\Unit\Events;

use App\Events\CsvInvitedUsersBatchFailed;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
#[Group('csv')]
class CsvInvitedUsersBatchFailedTest extends TestCase
{
    #[Test]
    public function it_broadcasts_on_correct_channel(): void
    {
        $event = new CsvInvitedUsersBatchFailed(
            'import-123',
            'financer-456',
            'user-789',
            1,
            50,
            'Database connection failed',
            'PDOException'
        );

        $channels = $event->broadcastOn();

        $this->assertCount(1, $channels);
        $this->assertInstanceOf(PrivateChannel::class, $channels[0]);
        $this->assertEquals('private-user.user-789', $channels[0]->name);
    }

    #[Test]
    public function it_broadcasts_with_correct_data(): void
    {
        $event = new CsvInvitedUsersBatchFailed(
            'import-123',
            'financer-456',
            'user-789',
            2,
            100,
            'Memory limit exceeded',
            'ErrorException'
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('import-123', $broadcastData['import_id']);
        $this->assertEquals('financer-456', $broadcastData['financer_id']);
        $this->assertEquals(2, $broadcastData['batch_number']);
        $this->assertEquals(100, $broadcastData['total_rows']);
        $this->assertEquals('Memory limit exceeded', $broadcastData['error']);
        $this->assertEquals('ErrorException', $broadcastData['exception_class']);
        $this->assertArrayHasKey('failed_at', $broadcastData);
    }

    #[Test]
    public function it_broadcasts_as_batch_failed(): void
    {
        $event = new CsvInvitedUsersBatchFailed(
            'import-123',
            'financer-456',
            'user-789',
            1,
            50,
            'Test error',
            'Exception'
        );

        $this->assertEquals('batch.failed', $event->broadcastAs());
    }

    #[Test]
    public function it_can_be_dispatched(): void
    {
        Event::fake();

        broadcast(new CsvInvitedUsersBatchFailed(
            'import-test',
            'financer-test',
            'user-test',
            3,
            75,
            'Network timeout',
            'TimeoutException'
        ));

        Event::assertDispatched(CsvInvitedUsersBatchFailed::class, function ($event): bool {
            return $event->importId === 'import-test'
                && $event->batchNumber === 3
                && $event->error === 'Network timeout';
        });
    }
}
