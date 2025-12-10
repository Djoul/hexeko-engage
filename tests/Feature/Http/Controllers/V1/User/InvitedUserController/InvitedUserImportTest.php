<?php

namespace Tests\Feature\Http\Controllers\V1\User\InvitedUserController;

use App\Actions\User\InvitedUser\ImportInvitedUsersFromFileAction;
use App\Enums\IDP\RoleDefaults;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\ExcelFileGenerator;
use Tests\Helpers\Facades\ModelFactory;
use Tests\ProtectedRouteTestCase;

#[Group('user')]
#[Group('import')]
#[Group('invited-user')]
class InvitedUserImportTest extends ProtectedRouteTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Use s3-local disk (same as controller/action in testing)
        Storage::fake('s3-local');
        Bus::fake();
    }

    #[Test]
    public function it_dispatches_import_job_to_queue_when_csv_uploaded(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create a CSV file
        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "John,Doe,john.doe@example.com\n";
        $csvContent .= "Jane,Smith,jane.smith@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import-csv', [
                'file' => $csvFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202); // HTTP_ACCEPTED
        $response->assertJsonStructure([
            'data' => [
                'message',
                'file_path',
                'financer_id',
            ],
            'message',
        ]);

        // Assert job was dispatched
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id;
        });
    }

    #[Test]
    public function it_stores_csv_file_before_dispatching_job(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "Test,User,test@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'test.csv',
            $csvContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import-csv', [
                'file' => $csvFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202);

        $filePath = $response->json('data.file_path');
        $this->assertNotNull($filePath);

        // Check file was stored
        Storage::disk('s3-local')->assertExists($filePath);

        // Check job was dispatched with correct file path
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class, function ($job) use ($filePath): bool {
            return $job->filePath === $filePath;
        });
    }

    #[Test]
    public function it_returns_accepted_status_for_queued_import(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create a proper CSV file with headers
        $csvContent = "first_name,last_name,email\n";
        $csvContent .= "Alice,Johnson,alice@example.com\n";

        $csvFile = UploadedFile::fake()->createWithContent(
            'users.csv',
            $csvContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import-csv', [
                'file' => $csvFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJson([
            'message' => 'File import has been queued successfully. You will receive updates via websocket.',
        ]);
    }

    #[Test]
    public function it_dispatches_import_job_for_xlsx_file(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create an XLSX file using ExcelFileGenerator
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john.doe@example.com'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane.smith@example.com'],
        ];
        $xlsxContent = ExcelFileGenerator::generate($data, 'xlsx');

        $xlsxFile = UploadedFile::fake()->createWithContent(
            'users.xlsx',
            $xlsxContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $xlsxFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => [
                'message',
                'import_id',
                'file_path',
                'financer_id',
            ],
            'message',
        ]);

        // Assert job was dispatched
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id;
        });
    }

    #[Test]
    public function it_dispatches_import_job_for_xls_file(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create an XLS file using ExcelFileGenerator
        $data = [
            ['first_name' => 'Alice', 'last_name' => 'Johnson', 'email' => 'alice@example.com'],
            ['first_name' => 'Bob', 'last_name' => 'Williams', 'email' => 'bob@example.com'],
        ];
        $xlsContent = ExcelFileGenerator::generate($data, 'xls');

        $xlsFile = UploadedFile::fake()->createWithContent(
            'users.xls',
            $xlsContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $xlsFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202);
        $response->assertJsonStructure([
            'data' => [
                'message',
                'import_id',
                'file_path',
                'financer_id',
            ],
            'message',
        ]);

        // Assert job was dispatched
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class, function ($job) use ($financer): bool {
            return $job->financerId === $financer->id;
        });
    }

    #[Test]
    public function it_validates_xlsx_file_mime_type(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create an invalid file (PDF disguised as XLSX)
        $invalidFile = UploadedFile::fake()->create('fake.xlsx', 100, 'application/pdf');

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $invalidFile,
                'financer_id' => $financer->id,
            ]);

        // Assert - should reject invalid MIME type
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    #[Test]
    public function it_handles_excel_file_with_optional_columns(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create XLSX with optional columns
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'phone' => '33123456789', 'external_id' => 'EXT001'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'phone' => '', 'external_id' => 'EXT002'],
        ];
        $xlsxContent = ExcelFileGenerator::generate($data, 'xlsx');

        $xlsxFile = UploadedFile::fake()->createWithContent(
            'users_with_optional.xlsx',
            $xlsxContent
        );

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $xlsxFile,
                'financer_id' => $financer->id,
            ]);

        // Assert
        $response->assertStatus(202);

        // Verify job was dispatched (actual data validation happens in action tests)
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class);
    }

    #[Test]
    public function it_rejects_file_larger_than_10mb(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Create a file larger than 10MB (10240 KB)
        $largeFile = UploadedFile::fake()->create('large.xlsx', 10241, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Act
        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $largeFile,
                'financer_id' => $financer->id,
            ]);

        // Assert - should reject file exceeding size limit
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('file');
    }

    #[Test]
    public function it_uses_new_import_endpoint_for_all_formats(): void
    {
        // Arrange
        $this->auth = $this->createAuthUser(RoleDefaults::FINANCER_ADMIN);
        $financer = ModelFactory::createFinancer();

        // Test CSV
        $csvContent = "first_name,last_name,email\nTest,User,test@example.com\n";
        $csvFile = UploadedFile::fake()->createWithContent('test.csv', $csvContent);

        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $csvFile,
                'financer_id' => $financer->id,
            ]);

        $response->assertStatus(202);

        // Test XLSX
        $data = [['first_name' => 'Test', 'last_name' => 'User', 'email' => 'test@example.com']];
        $xlsxContent = ExcelFileGenerator::generate($data, 'xlsx');
        $xlsxFile = UploadedFile::fake()->createWithContent('test.xlsx', $xlsxContent);

        $response = $this->actingAs($this->auth)
            ->postJson('/api/v1/invited-users/import', [
                'file' => $xlsxFile,
                'financer_id' => $financer->id,
            ]);

        $response->assertStatus(202);

        // Verify both dispatched the same action
        Bus::assertDispatched(ImportInvitedUsersFromFileAction::class, 2);
    }
}
