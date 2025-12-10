<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FileReaders;

use App\Services\FileReaders\CsvFileReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
#[Group('import')]
#[Group('invited-user')]
final class CsvFileReaderTest extends TestCase
{
    private CsvFileReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new CsvFileReader;

        // Set up fake storage for testing
        Storage::fake('s3-local');
    }

    #[Test]
    public function it_reads_valid_csv_file_with_comma_delimiter(): void
    {
        // Arrange
        $csvContent = "first_name,last_name,email,phone\nJohn,Doe,john@example.com,+33123456789\nJane,Smith,jane@example.com,+33987654321";
        $filePath = 'test-users.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Doe', $result['rows'][0]['last_name']);
        $this->assertEquals('john@example.com', $result['rows'][0]['email']);
        $this->assertEquals('+33123456789', $result['rows'][0]['phone']);
    }

    #[Test]
    public function it_reads_csv_file_with_semicolon_delimiter(): void
    {
        // Arrange
        $csvContent = "first_name;last_name;email\nJohn;Doe;john@example.com\nJane;Smith;jane@example.com";
        $filePath = 'test-users-semicolon.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
    }

    #[Test]
    public function it_reads_csv_file_with_tab_delimiter(): void
    {
        // Arrange
        $csvContent = "first_name\tlast_name\temail\nJohn\tDoe\tjohn@example.com\nJane\tSmith\tjane@example.com";
        $filePath = 'test-users-tab.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
    }

    #[Test]
    public function it_handles_empty_csv_file(): void
    {
        // Arrange
        $csvContent = '';
        $filePath = 'empty.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertEquals('CSV file is empty', $result['error']);
        $this->assertEmpty($result['rows']);
    }

    #[Test]
    public function it_handles_missing_file(): void
    {
        // Act
        $result = $this->reader->readAndValidate('non-existent.csv');

        // Assert
        $this->assertEquals('File not found', $result['error']);
        $this->assertEmpty($result['rows']);
    }

    #[Test]
    public function it_does_not_add_missing_required_headers(): void
    {
        // Arrange
        $csvContent = "first_name,last_name\nJohn,Doe\nJane,Smith";
        $filePath = 'incomplete-headers.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert - FileReader returns data as-is, validation happens at controller level
        $this->assertNull($result['error']);
        $this->assertArrayNotHasKey('email', $result['rows'][0]);
        $this->assertArrayHasKey('first_name', $result['rows'][0]);
        $this->assertArrayHasKey('last_name', $result['rows'][0]);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Doe', $result['rows'][0]['last_name']);
    }

    #[Test]
    public function it_does_not_add_missing_optional_headers(): void
    {
        // Arrange
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\nJane,Smith,jane@example.com";
        $filePath = 'no-phone.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert - FileReader returns data as-is, validation happens at controller level
        $this->assertNull($result['error']);
        $this->assertArrayNotHasKey('phone', $result['rows'][0]);
        $this->assertArrayNotHasKey('external_id', $result['rows'][0]);
        $this->assertArrayHasKey('first_name', $result['rows'][0]);
        $this->assertArrayHasKey('last_name', $result['rows'][0]);
        $this->assertArrayHasKey('email', $result['rows'][0]);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('john@example.com', $result['rows'][0]['email']);
    }

    #[Test]
    public function it_skips_rows_with_mismatched_column_count(): void
    {
        // Arrange
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\nJane,Smith\nAlice,Johnson,alice@example.com";
        $filePath = 'mismatched-columns.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']); // Only 2 valid rows
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Alice', $result['rows'][1]['first_name']);
    }

    #[Test]
    public function it_handles_quoted_values_with_delimiters(): void
    {
        // Arrange
        $csvContent = "first_name,last_name,email\n\"John, Jr.\",\"Doe, Sr.\",john@example.com\nJane,Smith,jane@example.com";
        $filePath = 'quoted-values.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John, Jr.', $result['rows'][0]['first_name']);
        $this->assertEquals('Doe, Sr.', $result['rows'][0]['last_name']);
    }

    #[Test]
    public function it_returns_supported_extensions(): void
    {
        // Act
        $extensions = $this->reader->getSupportedExtensions();

        // Assert
        $this->assertContains('csv', $extensions);
        $this->assertContains('txt', $extensions);
    }

    #[Test]
    public function it_returns_supported_mime_types(): void
    {
        // Act
        $mimeTypes = $this->reader->getSupportedMimeTypes();

        // Assert
        $this->assertContains('text/csv', $mimeTypes);
        $this->assertContains('application/csv', $mimeTypes);
    }

    #[Test]
    public function it_removes_empty_lines_from_csv(): void
    {
        // Arrange
        $csvContent = "first_name,last_name,email\nJohn,Doe,john@example.com\n\n\nJane,Smith,jane@example.com\n\n";
        $filePath = 'with-empty-lines.csv';
        Storage::disk('s3-local')->put($filePath, $csvContent);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Jane', $result['rows'][1]['first_name']);
    }
}
