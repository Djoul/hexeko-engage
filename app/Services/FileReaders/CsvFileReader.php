<?php

declare(strict_types=1);

namespace App\Services\FileReaders;

use App\Contracts\FileReaderInterface;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

final readonly class CsvFileReader implements FileReaderInterface
{
    /**
     * @return array{rows: array<int, array<string, mixed>>, error: string|null}
     */
    public function readAndValidate(string $filePath): array
    {
        try {
            Log::info('Reading CSV file', ['file_path' => $filePath]);

            // Use s3-local for local/testing environments, s3 for production
            $disk = app()->environment(['local', 'testing']) ? 's3-local' : 's3';

            // Check if file exists using Storage facade
            if (! Storage::disk($disk)->exists($filePath)) {
                Log::error('CSV file not found', [
                    'file_path' => $filePath,
                    'disk' => $disk,
                    'all_files' => Storage::disk($disk)->allFiles(),
                ]);

                return ['rows' => [], 'error' => 'File not found'];
            }

            // Get file content using Storage facade
            $content = Storage::disk($disk)->get($filePath);
            if ($content === null) {
                return ['rows' => [], 'error' => 'Unable to read file content'];
            }
            $lines = explode("\n", $content);

            // Remove empty lines
            $lines = array_filter($lines, fn (string $line): bool => trim($line) !== '');

            if ($lines === []) {
                return ['rows' => [], 'error' => 'CSV file is empty'];
            }

            // Detect CSV delimiter
            $headerLine = array_shift($lines);
            $delimiter = $this->detectCsvDelimiter($headerLine);

            Log::info('Detected CSV delimiter', ['delimiter' => $delimiter]);

            // Parse header with detected delimiter
            // Use empty string for escape parameter (PHP 8.4+ compatibility)
            $headers = str_getcsv($headerLine, $delimiter, '"', '');
            // Filter out null values and ensure we have strings
            $headers = array_map(fn (mixed $h): string => $h, array_filter($headers, fn (mixed $h): bool => $h !== null));

            Log::info('CSV headers', ['headers' => $headers]);

            // Parse rows
            $rows = [];
            foreach ($lines as $line) {
                $values = str_getcsv($line, $delimiter, '"', '');

                // Skip if row doesn't match header count
                if (count($values) !== count($headers)) {
                    continue;
                }

                // Create associative array from headers and values
                $row = array_combine($headers, $values);

                $rows[] = $row;
            }

            return ['rows' => $rows, 'error' => null];

        } catch (Exception $e) {
            Log::error('Failed to read CSV file', [
                'error' => $e->getMessage(),
                'file_path' => $filePath,
            ]);

            return ['rows' => [], 'error' => 'Failed to read CSV file: '.$e->getMessage()];
        }
    }

    /**
     * @return array<string>
     */
    public function getSupportedExtensions(): array
    {
        return ['csv', 'txt'];
    }

    /**
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        return [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/x-csv',
            'text/comma-separated-values',
            'text/x-comma-separated-values',
            'text/tab-separated-values',
        ];
    }

    /**
     * Detect the delimiter used in the CSV file
     *
     * @param  string  $line  Sample line from the CSV (usually the header)
     * @return string The detected delimiter
     */
    private function detectCsvDelimiter(string $line): string
    {
        // Common delimiters to check
        $delimiters = [
            ',' => 0,  // comma
            ';' => 0,  // semicolon
            "\t" => 0, // tab
            '|' => 0,  // pipe
        ];

        // Count occurrences of each delimiter in the line
        foreach ($delimiters as $delimiter => $count) {
            $delimiters[$delimiter] = substr_count($line, $delimiter);
        }

        // Check if there are quoted values that might contain delimiters
        // This helps avoid false positives from delimiters inside quoted fields
        $hasQuotes = str_contains($line, '"');

        if ($hasQuotes) {
            // Try to parse with each delimiter and see which gives the most fields
            $maxFields = 0;
            $bestDelimiter = ',';

            foreach ($delimiters as $delimiter => $count) {
                if ($count > 0) {
                    $fields = str_getcsv($line, $delimiter, '"', '');
                    $fieldCount = count($fields);

                    // Prefer delimiter that gives reasonable number of fields
                    // and has consistent field content
                    if ($fieldCount > $maxFields && $fieldCount >= 3) {
                        $maxFields = $fieldCount;
                        $bestDelimiter = $delimiter;
                    }
                }
            }

            return $bestDelimiter;
        }

        // If no quotes, use the delimiter with the highest count
        // Default to comma if no delimiter is found
        $maxCount = max($delimiters);
        if ($maxCount === 0) {
            return ','; // Default to comma if no delimiter found
        }

        $delimiter = array_search($maxCount, $delimiters, true);

        return $delimiter === false ? ',' : $delimiter;
    }
}
