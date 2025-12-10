<?php

declare(strict_types=1);

namespace App\Services\FileReaders;

use App\Contracts\FileReaderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Worksheet\Row;

final readonly class ExcelFileReader implements FileReaderInterface
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, error: string|null}
     */
    public function readAndValidate(string $filePath): array
    {
        try {
            Log::info('Reading Excel file', ['file_path' => $filePath]);

            // Use s3-local for local/testing environments, s3 for production
            $disk = app()->environment(['local', 'testing']) ? 's3-local' : 's3';

            // Check if file exists
            if (! Storage::disk($disk)->exists($filePath)) {
                Log::error('Excel file not found', [
                    'file_path' => $filePath,
                    'disk' => $disk,
                ]);

                return ['rows' => [], 'error' => 'File not found'];
            }

            // Create a temporary file to work with PhpSpreadsheet
            // (PhpSpreadsheet requires a file path, not content string)
            $tempPath = sys_get_temp_dir().'/'.uniqid('excel_', true).'.xlsx';
            $content = Storage::disk($disk)->get($filePath);

            if ($content === null) {
                return ['rows' => [], 'error' => 'Unable to read file content'];
            }

            file_put_contents($tempPath, $content);

            try {
                // Load spreadsheet with read-only mode for better performance
                $spreadsheet = IOFactory::load($tempPath);
                $worksheet = $spreadsheet->getActiveSheet();

                // Get row iterator for memory efficiency
                $rowIterator = $worksheet->getRowIterator();
                $headers = [];
                $rows = [];
                $isFirstRow = true;

                foreach ($rowIterator as $row) {
                    /** @var Row $row */
                    $cellIterator = $row->getCellIterator();
                    $cellIterator->setIterateOnlyExistingCells(false);

                    $rowData = [];
                    foreach ($cellIterator as $cell) {
                        $value = $cell->getValue();

                        // Handle date values
                        if (Date::isDateTime($cell)) {
                            $value = Date::excelToDateTimeObject($value)->format('Y-m-d');
                        }

                        $rowData[] = $value !== null ? (string) $value : '';
                    }

                    // Remove trailing empty cells
                    $rowData = $this->trimEmptyTrailingCells($rowData);

                    // Skip completely empty rows
                    if ($this->isRowEmpty($rowData)) {
                        continue;
                    }

                    // First non-empty row is headers
                    if ($isFirstRow) {
                        $headers = $rowData;
                        $isFirstRow = false;
                        Log::info('Excel headers', ['headers' => $headers]);

                        continue;
                    }

                    // Skip rows that don't match header count
                    if (count($rowData) !== count($headers)) {
                        Log::warning('Row count mismatch', [
                            'expected' => count($headers),
                            'actual' => count($rowData),
                            'row' => $rowData,
                        ]);

                        continue;
                    }

                    // Create associative array from headers and values
                    $row = array_combine($headers, $rowData);

                    $rows[] = $row;
                }

                // Clean up spreadsheet from memory
                $spreadsheet->disconnectWorksheets();
                unset($spreadsheet);

                if ($rows === []) {
                    return ['rows' => [], 'error' => 'Excel file contains no data rows'];
                }

                return ['rows' => $rows, 'error' => null];

            } finally {
                // Always clean up temp file
                if (file_exists($tempPath)) {
                    unlink($tempPath);
                }
            }

        } catch (Exception $e) {
            Log::error('Failed to read Excel file', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return ['rows' => [], 'error' => 'Failed to read Excel file: '.$e->getMessage()];
        }
    }

    /**
     * @return array<string>
     */
    public function getSupportedExtensions(): array
    {
        return ['xls', 'xlsx'];
    }

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        return [
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-office',
        ];
    }

    /**
     * Remove trailing empty cells from row data
     *
     * @param  array<int, string>  $rowData
     * @return array<int, string>
     */
    private function trimEmptyTrailingCells(array $rowData): array
    {
        while (count($rowData) > 0 && end($rowData) === '') {
            array_pop($rowData);
        }

        return $rowData;
    }

    /**
     * Check if row contains only empty values
     *
     * @param  array<int, string>  $rowData
     */
    private function isRowEmpty(array $rowData): bool
    {
        foreach ($rowData as $cell) {
            if (trim($cell) !== '') {
                return false;
            }
        }

        return true;
    }
}
