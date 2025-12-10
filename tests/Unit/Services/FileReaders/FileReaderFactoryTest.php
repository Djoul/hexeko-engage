<?php

declare(strict_types=1);

namespace Tests\Unit\Services\FileReaders;

use App\Services\FileReaders\CsvFileReader;
use App\Services\FileReaders\ExcelFileReader;
use App\Services\FileReaders\FileReaderFactory;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('user')]
#[Group('import')]
#[Group('invited-user')]
final class FileReaderFactoryTest extends TestCase
{
    private FileReaderFactory $factory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->factory = new FileReaderFactory;
    }

    #[Test]
    public function it_creates_csv_reader_for_csv_extension(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.csv');

        // Assert
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_csv_reader_for_txt_extension(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.txt');

        // Assert
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_excel_reader_for_xlsx_extension(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.xlsx');

        // Assert
        $this->assertInstanceOf(ExcelFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_excel_reader_for_xls_extension(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.xls');

        // Assert
        $this->assertInstanceOf(ExcelFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_csv_reader_for_csv_mime_type(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.unknown', 'text/csv');

        // Assert
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_excel_reader_for_xlsx_mime_type(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.unknown', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

        // Assert
        $this->assertInstanceOf(ExcelFileReader::class, $reader);
    }

    #[Test]
    public function it_creates_excel_reader_for_xls_mime_type(): void
    {
        // Act
        $reader = $this->factory->createFromFile('test.unknown', 'application/vnd.ms-excel');

        // Assert
        $this->assertInstanceOf(ExcelFileReader::class, $reader);
    }

    #[Test]
    public function it_throws_exception_for_unsupported_extension(): void
    {
        // Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file format: pdf');

        // Act
        $this->factory->createFromFile('test.pdf');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_mime_type(): void
    {
        // Expect
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported file format');

        // Act
        $this->factory->createFromFile('test.unknown', 'application/pdf');
    }

    #[Test]
    public function it_prefers_extension_over_mime_type(): void
    {
        // Act - CSV extension with Excel MIME type
        $reader = $this->factory->createFromFile('test.csv', 'application/vnd.ms-excel');

        // Assert - Should create CSV reader based on extension
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    #[Test]
    public function it_handles_uppercase_extensions(): void
    {
        // Act
        $reader = $this->factory->createFromFile('TEST.CSV');

        // Assert
        $this->assertInstanceOf(CsvFileReader::class, $reader);
    }

    #[Test]
    public function it_handles_mixed_case_extensions(): void
    {
        // Act
        $reader = $this->factory->createFromFile('Test.XlSx');

        // Assert
        $this->assertInstanceOf(ExcelFileReader::class, $reader);
    }

    #[Test]
    public function it_returns_all_supported_extensions(): void
    {
        // Act
        $extensions = $this->factory->getSupportedExtensions();

        // Assert
        $this->assertContains('csv', $extensions);
        $this->assertContains('txt', $extensions);
        $this->assertContains('xls', $extensions);
        $this->assertContains('xlsx', $extensions);
    }

    #[Test]
    public function it_returns_all_supported_mime_types(): void
    {
        // Act
        $mimeTypes = $this->factory->getSupportedMimeTypes();

        // Assert
        $this->assertContains('text/csv', $mimeTypes);
        $this->assertContains('application/csv', $mimeTypes);
        $this->assertContains('application/vnd.ms-excel', $mimeTypes);
        $this->assertContains('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $mimeTypes);
    }
}
