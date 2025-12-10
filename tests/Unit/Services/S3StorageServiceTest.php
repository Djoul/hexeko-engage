<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\TranslationMigrations\S3StorageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

#[Group('admin-panel')]
#[Group('translation')]
class S3StorageServiceTest extends TestCase
{
    private S3StorageService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Use Storage fake for testing environment
        Storage::fake('translations-s3-local');

        $this->service = new S3StorageService;
    }

    #[Test]
    public function it_lists_migration_files_for_interface(): void
    {
        // Arrange
        $interface = 'web_financer';
        $disk = Storage::disk('translations-s3-local');

        // Create test files in the fake filesystem
        $disk->put('migrations/web_financer/web_financer_v1.0.0_2025-01-01.json', '{"test": true}');
        $disk->put('migrations/web_financer/web_financer_v1.0.1_2025-01-02.json', '{"test": true}');
        $disk->put('migrations/web_financer/web_financer_v1.0.2_2025-01-03.json', '{"test": true}');

        // Act
        $files = $this->service->listMigrationFiles($interface);

        // Assert
        $this->assertInstanceOf(Collection::class, $files);
        $this->assertCount(3, $files);
        $this->assertStringContainsString('v1.0.0', $files[0]);
        $this->assertStringContainsString('v1.0.1', $files[1]);
        $this->assertStringContainsString('v1.0.2', $files[2]);
    }

    #[Test]
    public function it_uploads_migration_file(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $content = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        $expectedPath = "migrations/{$interface}/{$filename}";
        $disk = Storage::disk('translations-s3-local');

        // Act
        $result = $this->service->uploadMigrationFile($interface, $filename, $content);

        // Assert
        $this->assertTrue($result);
        $disk->assertExists($expectedPath);
        $this->assertEquals($content, $disk->get($expectedPath));
    }

    #[Test]
    public function it_downloads_existing_migration(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $expectedPath = "migrations/{$interface}/{$filename}";
        $expectedContent = json_encode(['key1' => 'value1', 'key2' => 'value2']);
        $disk = Storage::disk('translations-s3-local');

        // Create the file in fake filesystem
        $disk->put($expectedPath, $expectedContent);

        // Act
        $content = $this->service->downloadMigrationFile($interface, $filename);

        // Assert
        $this->assertEquals($expectedContent, $content);
    }

    #[Test]
    public function it_throws_exception_for_missing_file(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'non_existent.json';
        $expectedPath = "migrations/{$interface}/{$filename}";

        // No need to create the file - it doesn't exist in fake filesystem

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Migration file not found: {$expectedPath}");

        $this->service->downloadMigrationFile($interface, $filename);
    }

    #[Test]
    public function it_deletes_migration_file(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $expectedPath = "migrations/{$interface}/{$filename}";
        $disk = Storage::disk('translations-s3-local');

        // Create the file first
        $disk->put($expectedPath, '{"test": true}');
        $disk->assertExists($expectedPath);

        // Act
        $result = $this->service->deleteMigrationFile($interface, $filename);

        // Assert
        $this->assertTrue($result);
        $disk->assertMissing($expectedPath);
    }

    #[Test]
    public function it_checks_if_migration_file_exists(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $expectedPath = "migrations/{$interface}/{$filename}";
        $disk = Storage::disk('translations-s3-local');

        // Create the file
        $disk->put($expectedPath, '{"test": true}');

        // Act
        $exists = $this->service->migrationFileExists($interface, $filename);

        // Assert
        $this->assertTrue($exists);

        // Test non-existent file
        $notExists = $this->service->migrationFileExists($interface, 'nonexistent.json');
        $this->assertFalse($notExists);
    }

    #[Test]
    public function it_creates_backup_of_existing_translations(): void
    {
        // Arrange
        $interface = 'web_financer';
        $version = 'v1.0.0';
        $content = json_encode(['key1' => 'value1']);
        $disk = Storage::disk('translations-s3-local');

        // Act
        $backupPath = $this->service->createBackup($interface, $version, $content);

        // Assert
        $this->assertStringStartsWith("backups/{$interface}/{$interface}_backup_{$version}_", $backupPath);
        $this->assertStringEndsWith('.json', $backupPath);
        $disk->assertExists($backupPath);
        $this->assertEquals($content, $disk->get($backupPath));
    }

    #[Test]
    public function it_lists_backup_files_for_interface(): void
    {
        // Arrange
        $interface = 'web_financer';
        $disk = Storage::disk('translations-s3-local');

        // Create test backup files
        $disk->put('backups/web_financer/web_financer_backup_v1.0.0_2025-01-01_120000.json', '{"backup": true}');
        $disk->put('backups/web_financer/web_financer_backup_v1.0.1_2025-01-02_130000.json', '{"backup": true}');

        // Act
        $backups = $this->service->listBackupFiles($interface);

        // Assert
        $this->assertInstanceOf(Collection::class, $backups);
        $this->assertCount(2, $backups);
        $this->assertStringContainsString('backup', $backups[0]);
    }

    #[Test]
    public function it_calculates_file_checksum(): void
    {
        // Arrange
        $interface = 'web_financer';
        $filename = 'web_financer_v1.0.0_2025-01-01.json';
        $expectedPath = "migrations/{$interface}/{$filename}";
        $content = '{"key1": "value1", "key2": "value2"}';
        $expectedChecksum = hash('sha256', $content);
        $disk = Storage::disk('translations-s3-local');

        // Create the file
        $disk->put($expectedPath, $content);

        // Act
        $checksum = $this->service->getFileChecksum($interface, $filename);

        // Assert
        $this->assertEquals($expectedChecksum, $checksum);
    }
}
