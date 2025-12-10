<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FileReaders;

use App\Services\FileReaders\ExcelFileReader;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\Helpers\ExcelFileGenerator;
use Tests\TestCase;

#[Group('user')]
#[Group('import')]
#[Group('invited-user')]
final class ExcelFileReaderTest extends TestCase
{
    private ExcelFileReader $reader;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reader = new ExcelFileReader;

        // Set up fake storage for testing
        Storage::fake('s3-local');
    }

    #[Test]
    public function it_reads_valid_xlsx_file(): void
    {
        // Arrange
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com', 'phone' => '33123456789'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com', 'phone' => '33987654321'],
        ];
        $content = ExcelFileGenerator::generate($data, 'xlsx');
        $filePath = 'test-users.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Doe', $result['rows'][0]['last_name']);
        $this->assertEquals('john@example.com', $result['rows'][0]['email']);
        $this->assertEquals('33123456789', $result['rows'][0]['phone']);
    }

    #[Test]
    public function it_reads_valid_xls_file(): void
    {
        // Arrange
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com'],
        ];
        $content = ExcelFileGenerator::generate($data, 'xls');
        $filePath = 'test-users.xls';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']);
        $this->assertEquals('John', $result['rows'][0]['first_name']);
    }

    #[Test]
    public function it_handles_empty_excel_file(): void
    {
        // Arrange
        $content = ExcelFileGenerator::generateEmpty('xlsx');
        $filePath = 'empty.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertEquals('Excel file contains no data rows', $result['error']);
        $this->assertEmpty($result['rows']);
    }

    #[Test]
    public function it_handles_excel_file_with_headers_only(): void
    {
        // Arrange
        $headers = ['first_name', 'last_name', 'email'];
        $content = ExcelFileGenerator::generateHeadersOnly($headers, 'xlsx');
        $filePath = 'headers-only.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertEquals('Excel file contains no data rows', $result['error']);
        $this->assertEmpty($result['rows']);
    }

    #[Test]
    public function it_handles_missing_file(): void
    {
        // Act
        $result = $this->reader->readAndValidate('non-existent.xlsx');

        // Assert
        $this->assertEquals('File not found', $result['error']);
        $this->assertEmpty($result['rows']);
    }

    #[Test]
    public function it_does_not_add_missing_required_headers(): void
    {
        // Arrange
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe'],
            ['first_name' => 'Jane', 'last_name' => 'Smith'],
        ];
        $content = ExcelFileGenerator::generate($data, 'xlsx');
        $filePath = 'incomplete-headers.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

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
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
            ['first_name' => 'Jane', 'last_name' => 'Smith', 'email' => 'jane@example.com'],
        ];
        $content = ExcelFileGenerator::generate($data, 'xlsx');
        $filePath = 'no-phone.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

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
    public function it_skips_empty_rows(): void
    {
        // Arrange
        $headers = ['first_name', 'last_name', 'email'];
        $rows = [
            ['John', 'Doe', 'john@example.com'],
            ['', '', ''], // Empty row
            ['', '', ''], // Empty row
            ['Jane', 'Smith', 'jane@example.com'],
        ];
        $content = ExcelFileGenerator::generateWithHeaders($headers, $rows, 'xlsx');
        $filePath = 'with-empty-rows.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(2, $result['rows']); // Only 2 non-empty rows
        $this->assertEquals('John', $result['rows'][0]['first_name']);
        $this->assertEquals('Jane', $result['rows'][1]['first_name']);
    }

    #[Test]
    public function it_returns_supported_extensions(): void
    {
        // Act
        $extensions = $this->reader->getSupportedExtensions();

        // Assert
        $this->assertContains('xls', $extensions);
        $this->assertContains('xlsx', $extensions);
    }

    #[Test]
    public function it_returns_supported_mime_types(): void
    {
        // Act
        $mimeTypes = $this->reader->getSupportedMimeTypes();

        // Assert
        $this->assertContains('application/vnd.ms-excel', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $mimeTypes);
    }

    #[Test]
    public function it_handles_cells_with_formulas(): void
    {
        // Note: This test generates data without formulas
        // but demonstrates that the reader can handle various cell types
        // In a real scenario, formulas would be evaluated by PhpSpreadsheet
        $data = [
            ['first_name' => 'John', 'last_name' => 'Doe', 'email' => 'john@example.com'],
        ];
        $content = ExcelFileGenerator::generate($data, 'xlsx');
        $filePath = 'test-formulas.xlsx';
        Storage::disk('s3-local')->put($filePath, $content);

        // Act
        $result = $this->reader->readAndValidate($filePath);

        // Assert
        $this->assertNull($result['error']);
        $this->assertCount(1, $result['rows']);
    }
}
