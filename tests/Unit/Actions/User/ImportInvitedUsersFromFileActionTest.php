<?php

namespace Tests\Unit\Actions\User;

use App\Actions\User\InvitedUser\ImportInvitedUsersFromFileAction;
use App\Events\CsvInvitedUsersImportCompleted;
use App\Events\CsvInvitedUsersImportStarted;
use App\Jobs\HandleCsvImportBatchCompletionJob;
use App\Jobs\ProcessCsvInvitedUsersBatchJob;
use App\Services\CsvImportTrackerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\Facades\ModelFactory;
use Tests\TestCase;

#[Group('user')]
#[Group('import')]
#[Group('invited-user')]
class ImportInvitedUsersFromFileActionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('s3-local');
        Bus::fake();
        Event::fake();

        // Mock Redis to avoid connection errors in CI
        $this->mockRedis();
    }

    private function mockRedis(): void
    {
        // Mock the CsvImportTrackerService to avoid Redis calls
        $trackerMock = Mockery::mock(CsvImportTrackerService::class);
        $trackerMock->shouldReceive('initializeImport')->andReturn(null);
        $trackerMock->shouldReceive('storeImportMetadata')->andReturn(null);

        $this->app->instance(CsvImportTrackerService::class, $trackerMock);
    }

    #[Test]
    public function it_reads_csv_and_dispatches_batch_jobs(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // Create a CSV file with 120 rows (should create 3 batches of 50)
        $csvContent = "first_name,last_name,email\n";
        for ($i = 1; $i <= 120; $i++) {
            $csvContent .= "User{$i},Lastname{$i},user{$i}@example.com\n";
        }

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        // Store the file
        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert - should dispatch individual jobs (not batches anymore)
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, 3);
        Bus::assertDispatched(HandleCsvImportBatchCompletionJob::class, 1);
    }

    #[Test]
    public function it_chunks_csv_into_batches_of_50(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // Create CSV with 120 rows
        $csvContent = "first_name,last_name,email\n";
        for ($i = 1; $i <= 120; $i++) {
            $csvContent .= "User{$i},Lastname{$i},user{$i}@example.com\n";
        }

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert - should dispatch 3 individual jobs (120 rows / 50 per batch)
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, 3);

        // Verify each batch
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, function ($job): bool {
            if ($job->batchNumber === 1) {
                return count($job->rows) === 50;
            }
            if ($job->batchNumber === 2) {
                return count($job->rows) === 50;
            }
            if ($job->batchNumber === 3) {
                return count($job->rows) === 20;
            }

            return false;
        });
    }

    #[Test]
    public function it_sends_websocket_event_at_start_with_total_count(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $csvContent = "first_name,last_name,email\n";
        for ($i = 1; $i <= 75; $i++) {
            $csvContent .= "User{$i},Lastname{$i},user{$i}@example.com\n";
        }

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $importId = $action->handle();

        // Assert - ImportStarted event dispatched with total count
        Event::assertDispatched(CsvInvitedUsersImportStarted::class, function ($event) use ($financer, $importId): bool {
            return $event->financerId === $financer->id
                && $event->totalRows === 75
                && $event->totalBatches === 2
                && $event->importId === $importId;
        });
    }

    #[Test]
    public function it_handles_invalid_csv_headers_gracefully(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // CSV with invalid headers - the action now creates empty values for missing headers
        $csvContent = "wrong_header,another_wrong\n";
        $csvContent .= "value1,value2\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'invalid.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert - should still dispatch batch job with empty values for required fields
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, 1);

        // Verify that the job contains rows with empty required fields
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, function ($job): bool {
            $row = $job->rows[0] ?? [];

            return isset($row['first_name']) && $row['first_name'] === '' &&
                   isset($row['last_name']) && $row['last_name'] === '' &&
                   isset($row['email']) && $row['email'] === '';
        });
    }

    #[Test]
    public function it_handles_empty_csv_file(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $csvContent = "first_name,last_name,email\n"; // Only headers

        $csvFile = UploadedFile::fake()->createWithContent(
            'empty.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert
        Bus::assertNothingBatched();

        Event::assertDispatched(CsvInvitedUsersImportCompleted::class, function ($event): bool {
            return $event->totalRows === 0 && $event->status === 'completed';
        });
    }

    #[Test]
    public function it_validates_csv_headers_before_processing(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // Missing required headers - the action now creates empty values for missing headers
        $csvContent = "name,email\n"; // Missing first_name, last_name
        $csvContent .= "John,john@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'invalid_headers.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert - should still dispatch batch job with original fields plus empty required fields
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, 1);

        // Verify that the job contains rows with original data and empty required fields
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, function ($job): bool {
            $row = $job->rows[0] ?? [];

            return isset($row['name']) && $row['name'] === 'John' &&
                   isset($row['email']) && $row['email'] === 'john@example.com' &&
                   isset($row['first_name']) && $row['first_name'] === '' &&
                   isset($row['last_name']) && $row['last_name'] === '';
        });
    }

    #[Test]
    public function it_tracks_import_job_id_for_monitoring(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "John,Doe,john@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $importId = $action->handle();

        // Assert
        $this->assertNotNull($importId);
        $this->assertIsString($importId);

        // Check that dispatched jobs receive the import ID
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, function ($job) use ($importId): bool {
            return $job->importId === $importId;
        });
    }

    #[Test]
    public function it_includes_optional_csv_columns_in_batch(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        // CSV with optional columns
        $csvContent = "first_name,last_name,email,phone,external_id\n";
        $csvContent .= "John,Doe,john@example.com,+33123456789,EXT001\n";
        $csvContent .= "Jane,Smith,jane@example.com,,EXT002\n"; // No phone

        $csvFile = UploadedFile::fake()->createWithContent(
            'users_with_optional.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $action->handle();

        // Assert
        Bus::assertDispatched(ProcessCsvInvitedUsersBatchJob::class, function ($job): bool {
            if (count($job->rows) !== 2) {
                return false;
            }

            $firstRow = $job->rows[0];
            $secondRow = $job->rows[1];

            return $firstRow['phone'] === '+33123456789' &&
                   $firstRow['external_id'] === 'EXT001' &&
                   $secondRow['phone'] === '' &&
                   $secondRow['external_id'] === 'EXT002';
        });
    }

    #[Test]
    public function it_sets_batch_completion_callback(): void
    {
        // Arrange
        $financer = ModelFactory::createFinancer();

        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "John,Doe,john@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        $filePath = $csvFile->store('imports', 's3-local');

        // Act
        $action = new ImportInvitedUsersFromFileAction($filePath, $financer->id, 'user-123');
        $importId = $action->handle();

        // Assert - completion job should be dispatched with correct parameters
        Bus::assertDispatched(HandleCsvImportBatchCompletionJob::class, function ($job) use ($financer, $importId): bool {
            return $job->importId === $importId &&
                   $job->financerId === $financer->id &&
                   $job->userId === 'user-123';
        });
    }
}
