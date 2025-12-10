<?php

namespace Tests\Unit\Events;

use App\Events\CsvInvitedUsersImportCompleted;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
#[Group('csv')]
class CsvInvitedUsersImportCompletedTest extends TestCase
{
    #[Test]
    public function it_limits_failed_rows_details_in_broadcast_payload(): void
    {
        // Create a large array of failed rows
        $failedRowsDetails = [];
        for ($i = 0; $i < 200; $i++) {
            $failedRowsDetails[] = [
                'row' => [
                    'email' => "user{$i}@example.com",
                    'first_name' => "FirstName{$i}",
                    'last_name' => "LastName{$i}",
                    'phone' => "+1-555-{$i}",
                    'external_id' => "EXT{$i}",
                ],
                'error' => 'Email already exists for this financer',
            ];
        }

        $event = new CsvInvitedUsersImportCompleted(
            importId: 'test-import-123',
            financerId: 'financer-456',
            userId: 'user-789',
            totalRows: 200,
            processedRows: 0,
            failedRows: 200,
            status: 'completed',
            error: null,
            failedRowsDetails: $failedRowsDetails
        );

        $broadcastData = $event->broadcastWith();

        // Should limit to 10 errors
        $this->assertCount(10, $broadcastData['failed_rows_details']);
        $this->assertTrue($broadcastData['has_more_errors']);
        $this->assertEquals(200, $broadcastData['total_errors']);
    }

    #[Test]
    public function it_includes_all_errors_when_below_limit(): void
    {
        $failedRowsDetails = [
            [
                'row' => ['email' => 'user1@example.com'],
                'error' => 'Error 1',
            ],
            [
                'row' => ['email' => 'user2@example.com'],
                'error' => 'Error 2',
            ],
        ];

        $event = new CsvInvitedUsersImportCompleted(
            importId: 'test-import-123',
            financerId: 'financer-456',
            userId: 'user-789',
            totalRows: 10,
            processedRows: 8,
            failedRows: 2,
            status: 'completed',
            error: null,
            failedRowsDetails: $failedRowsDetails
        );

        $broadcastData = $event->broadcastWith();

        $this->assertCount(2, $broadcastData['failed_rows_details']);
        $this->assertFalse($broadcastData['has_more_errors']);
        $this->assertEquals(2, $broadcastData['total_errors']);
    }

    #[Test]
    public function it_includes_error_summary_in_broadcast_payload(): void
    {
        $failedRowsDetails = [];
        // Create different error types
        for ($i = 0; $i < 50; $i++) {
            $failedRowsDetails[] = [
                'row' => ['email' => "user{$i}@example.com"],
                'error' => 'Email already exists for this financer',
            ];
        }
        for ($i = 50; $i < 70; $i++) {
            $failedRowsDetails[] = [
                'row' => ['email' => "user{$i}@example.com"],
                'error' => 'Invalid email format',
            ];
        }
        for ($i = 70; $i < 80; $i++) {
            $failedRowsDetails[] = [
                'row' => ['email' => "user{$i}@example.com"],
                'error' => 'Missing required field: first_name',
            ];
        }

        $event = new CsvInvitedUsersImportCompleted(
            importId: 'test-import-123',
            financerId: 'financer-456',
            userId: 'user-789',
            totalRows: 100,
            processedRows: 20,
            failedRows: 80,
            status: 'completed',
            error: null,
            failedRowsDetails: $failedRowsDetails
        );

        $broadcastData = $event->broadcastWith();

        // Check error summary
        $this->assertArrayHasKey('error_summary', $broadcastData);
        $this->assertEquals(50, $broadcastData['error_summary']['Email already exists for this financer']);
        $this->assertEquals(20, $broadcastData['error_summary']['Invalid email format']);
        $this->assertEquals(10, $broadcastData['error_summary']['Missing required field: first_name']);
    }

    #[Test]
    public function it_deletes_long_error_messages_in_details(): void
    {
        $longError = str_repeat('This is a very long error message. ', 50);
        $failedRowsDetails = [
            [
                'row' => ['email' => 'user@example.com'],
                'error' => $longError,
            ],
        ];

        $event = new CsvInvitedUsersImportCompleted(
            importId: 'test-import-123',
            financerId: 'financer-456',
            userId: 'user-789',
            totalRows: 1,
            processedRows: 0,
            failedRows: 1,
            status: 'completed',
            error: null,
            failedRowsDetails: $failedRowsDetails
        );

        $broadcastData = $event->broadcastWith();

        // Error message should be deleted to 200 characters
        $this->assertLessThanOrEqual(203, strlen($broadcastData['failed_rows_details'][0]['error'])); // 200 + '...'
    }

    #[Test]
    public function it_preserves_other_broadcast_data(): void
    {
        $event = new CsvInvitedUsersImportCompleted(
            importId: 'test-import-123',
            financerId: 'financer-456',
            userId: 'user-789',
            totalRows: 100,
            processedRows: 95,
            failedRows: 5,
            status: 'completed',
            error: null,
            failedRowsDetails: [],
            totalDuration: 12.5,
            startedAt: '2024-01-01T10:00:00Z',
            completedAt: '2024-01-01T10:00:12Z'
        );

        $broadcastData = $event->broadcastWith();

        $this->assertEquals('test-import-123', $broadcastData['import_id']);
        $this->assertEquals('financer-456', $broadcastData['financer_id']);
        $this->assertEquals(100, $broadcastData['total_rows']);
        $this->assertEquals(95, $broadcastData['processed_rows']);
        $this->assertEquals(5, $broadcastData['failed_rows']);
        $this->assertEquals('completed', $broadcastData['status']);
        $this->assertEquals(12.5, $broadcastData['total_duration_seconds']);
        $this->assertEquals('2024-01-01T10:00:00Z', $broadcastData['started_at']);
        $this->assertEquals('2024-01-01T10:00:12Z', $broadcastData['completed_at']);
    }
}
