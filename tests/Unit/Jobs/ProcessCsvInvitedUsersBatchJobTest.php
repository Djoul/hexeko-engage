<?php

namespace Tests\Unit\Jobs;

use App\Events\CsvInvitedUsersBatchProcessed;
use App\Jobs\ProcessCsvInvitedUsersBatchJob;
use App\Jobs\SendWelcomeEmailJob;
use App\Models\User;
use App\Services\CsvImportTrackerService;
use App\Services\Models\InvitedUserService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Attributes\FlushTables;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[FlushTables(tables: ['users', 'financer_user'], scope: 'test')]
#[Group('user')]
#[Group('jobs')]
class ProcessCsvInvitedUsersBatchJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Event::fake();

        // Mock CsvImportTrackerService to avoid Redis connection
        $trackerMock = Mockery::mock(CsvImportTrackerService::class);
        $trackerMock->shouldReceive('updateBatchProgress')->withAnyArgs()->andReturn(null);
        $this->app->instance(CsvImportTrackerService::class, $trackerMock);
    }

    #[Test]
    public function it_processes_batch_of_valid_users(): void
    {
        $this->withoutExceptionHandling();
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-123';
        $batchNumber = 1;

        $rows = [
            [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe.'.uniqid().'@example.com',
                'phone' => '+33123456789',
                'external_id' => 'EXT001',
            ],
            [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'email' => 'jane.smith.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Users were created with invitation_status='pending'
        $this->assertDatabaseHas('users', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => $rows[0]['email'],
            'invitation_status' => 'pending',
        ]);

        $this->assertDatabaseHas('users', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => $rows[1]['email'],
            'invitation_status' => 'pending',
        ]);

        // Assert - Welcome email jobs were queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 2);

        // Assert - Batch processed event was dispatched
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event) use ($importId, $batchNumber): bool {
            return $event->importId === $importId &&
                   $event->batchNumber === $batchNumber &&
                   $event->processedCount === 2 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_handles_invalid_email_in_batch(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-456';
        $batchNumber = 2;
        $validEmail = 'valid.'.uniqid().'@example.com';

        $rows = [
            [
                'first_name' => 'Valid',
                'last_name' => 'User',
                'email' => $validEmail,
                'phone' => '',
                'external_id' => '',
            ],
            [
                'first_name' => 'Invalid',
                'last_name' => 'Email',
                'email' => 'invalid-email', // Invalid email
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Only valid user was created
        $this->assertDatabaseHas('users', [
            'email' => $validEmail,
            'invitation_status' => 'pending',
        ]);

        $this->assertDatabaseMissing('users', [
            'email' => 'invalid-email',
        ]);

        // Assert - Only one welcome email job queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 1);

        // Assert - Event shows one success, one failure
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 1;
        });
    }

    #[Test]
    public function it_handles_duplicate_emails_for_same_financer(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-789';
        $batchNumber = 3;
        $existingEmail = 'existing.'.uniqid().'@example.com';
        $newEmail = 'new.'.uniqid().'@example.com';

        // Pre-create an existing ACTIVE user for SAME financer
        ModelFactory::createUser([
            'email' => $existingEmail,
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => now(), 'role' => 'beneficiary'],
            ],
        ]);

        $rows = [
            [
                'first_name' => 'New',
                'last_name' => 'User',
                'email' => $newEmail,
                'phone' => '',
                'external_id' => '',
            ],
            [
                'first_name' => 'Duplicate',
                'last_name' => 'User',
                'email' => $existingEmail, // Already exists for SAME financer as active
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - New user was created successfully
        $this->assertDatabaseHas('users', [
            'email' => $newEmail,
            'invitation_status' => 'pending',
        ]);

        // Assert - Event shows one success, one failure (duplicate for same financer rejected)
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 1;
        });
    }

    #[Test]
    public function it_allows_same_email_for_different_financers_in_batch(): void
    {
        // Arrange
        $financer1 = ModelFactory::createFinancer();
        $financer2 = ModelFactory::createFinancer();
        $importId = 'import-multi-financer';
        $batchNumber = 12;
        $sharedEmail = 'shared.'.uniqid().'@example.com';

        // Pre-create an existing active user for financer1
        ModelFactory::createUser([
            'email' => $sharedEmail,
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer1, 'active' => true, 'from' => now(), 'role' => 'beneficiary'],
            ],
        ]);

        // Try to import SAME email for financer2 (should succeed)
        $rows = [
            [
                'first_name' => 'Shared',
                'last_name' => 'User',
                'email' => $sharedEmail,
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer2->id, // Different financer
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - User was created successfully for financer2
        $users = User::where('email', $sharedEmail)->get();
        $this->assertGreaterThanOrEqual(2, $users->count(), 'Should have at least 2 users with same email');

        // Verify one user is attached to financer1 and another to financer2
        $userWithFinancer2 = $users->first(function ($user) use ($financer2) {
            return $user->financers->contains($financer2);
        });
        $this->assertNotNull($userWithFinancer2, 'Should have user attached to financer2');

        // Assert - Event shows success
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 1 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_handles_missing_required_fields(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-999';
        $batchNumber = 4;

        $rows = [
            [
                'first_name' => '', // Missing required field
                'last_name' => 'User',
                'email' => 'test.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
            ],
            [
                'first_name' => 'Test',
                'last_name' => '', // Missing required field
                'email' => 'test2.'.uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - No email jobs queued
        Queue::assertNothingPushed();

        // Assert - Event shows all failures
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 0 &&
                   $event->failedCount === 2;
        });
    }

    #[Test]
    public function it_processes_large_batch_efficiently(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-large';
        $batchNumber = 5;

        // Create 50 rows (max batch size) with unique emails
        $rows = [];
        for ($i = 1; $i <= 50; $i++) {
            $rows[] = [
                'first_name' => "User{$i}",
                'last_name' => "Last{$i}",
                'email' => "user{$i}.".uniqid().'@example.com',
                'phone' => '',
                'external_id' => '',
            ];
        }

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - All email jobs queued
        Queue::assertPushed(SendWelcomeEmailJob::class, 50);

        // Assert - Event shows all processed successfully
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->processedCount === 50 &&
                   $event->failedCount === 0;
        });
    }

    #[Test]
    public function it_includes_batch_details_in_event(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-details';
        $batchNumber = 10;

        $rows = [
            [
                'first_name' => 'Test',
                'last_name' => 'User',
                'email' => 'test.'.uniqid().'@example.com',
                'phone' => '+33123456789',
                'external_id' => 'EXT123',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Event includes all details
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event) use ($importId, $batchNumber, $financer): bool {
            return $event->importId === $importId &&
                   $event->batchNumber === $batchNumber &&
                   $event->financerId === $financer->id &&
                   $event->processedCount === 1 &&
                   $event->failedCount === 0 &&
                   is_array($event->failedRows) &&
                   count($event->failedRows) === 0;
        });
    }

    #[Test]
    public function it_tracks_failed_rows_with_error_details(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-errors';
        $batchNumber = 11;

        $rows = [
            [
                'first_name' => 'Invalid',
                'last_name' => 'Email',
                'email' => 'not-an-email', // Invalid email
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - Event includes failed row details
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event): bool {
            return $event->failedCount === 1 &&
                   is_array($event->failedRows) &&
                   count($event->failedRows) === 1 &&
                   $event->failedRows[0]['row']['email'] === 'not-an-email' &&
                   isset($event->failedRows[0]['error']);
        });
    }

    #[Test]
    public function it_handles_database_constraint_violations_for_duplicate_emails(): void
    {
        // This test simulates the race condition bug (UE-728):
        // When multiple batches process duplicate emails in parallel,
        // the database constraint violation should be caught and displayed
        // with a consistent error message.

        // Arrange
        $financer = ModelFactory::createFinancer();
        $importId = 'import-race-condition';
        $batchNumber = 12;
        $duplicateEmail = 'race.condition.'.uniqid().'@example.com';

        // Pre-create an existing active user for the SAME financer
        // This ensures all duplicate attempts will be caught
        ModelFactory::createUser([
            'email' => $duplicateEmail,
            'invitation_status' => 'pending',
            'financers' => [
                ['financer' => $financer, 'active' => true, 'from' => now(), 'role' => 'beneficiary'],
            ],
        ]);

        // Create multiple duplicate emails in the same batch
        // This simulates what happens when parallel batches try to create the same email
        $rows = [
            [
                'first_name' => 'Duplicate1',
                'last_name' => 'User',
                'email' => $duplicateEmail,
                'phone' => '',
                'external_id' => '',
            ],
            [
                'first_name' => 'Duplicate2',
                'last_name' => 'User',
                'email' => $duplicateEmail,
                'phone' => '',
                'external_id' => '',
            ],
            [
                'first_name' => 'Duplicate3',
                'last_name' => 'User',
                'email' => $duplicateEmail,
                'phone' => '',
                'external_id' => '',
            ],
        ];

        $job = new ProcessCsvInvitedUsersBatchJob(
            $rows,
            $financer->id,
            'user-123',
            $importId,
            $batchNumber
        );

        // Act
        $job->handle(app(InvitedUserService::class));

        // Assert - All duplicates should be caught with consistent error message
        Event::assertDispatched(CsvInvitedUsersBatchProcessed::class, function ($event) use ($duplicateEmail): bool {
            // All 3 should fail
            if ($event->processedCount !== 0 || $event->failedCount !== 3) {
                return false;
            }

            // Check that all failed rows are for the duplicate email
            if (count($event->failedRows) !== 3) {
                return false;
            }

            // The bug was that some duplicates showed different error messages
            // Now they should all show "Email already exists" consistently
            $allowedMessages = [
                'Email already exists in database', // From QueryException catch
                'Email already exists for this financer', // From manual check in transaction
                'Duplicate email in batch', // From in-batch duplicate check
            ];

            foreach ($event->failedRows as $failedRow) {
                if ($failedRow['row']['email'] !== $duplicateEmail) {
                    return false;
                }

                if (! in_array($failedRow['error'], $allowedMessages)) {
                    return false;
                }
            }

            return true;
        });

        // Assert - No new users were created (only the pre-existing one remains)
        $this->assertEquals(
            1,
            User::where('email', $duplicateEmail)->count(),
            'Only the pre-existing user should exist, no duplicates created'
        );
    }
}
