<?php

declare(strict_types=1);

namespace Tests\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ExcelFileGenerator
{
    /**
     * Generate Excel file (XLSX or XLS) with provided data
     *
     * @param  array<array<string, mixed>>  $data  Array of rows (each row is associative array)
     * @param  string  $format  Either 'xlsx' or 'xls'
     * @return string Binary content of the Excel file
     */
    public static function generate(array $data, string $format = 'xlsx'): string
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        if ($data === []) {
            // Return empty spreadsheet
            return self::writeToString($spreadsheet, $format);
        }

        // Extract headers from first row
        $headers = array_keys($data[0]);
        $worksheet->fromArray($headers, null, 'A1');

        // Add data rows
        $rowNumber = 2; // Start from row 2 (row 1 is headers)
        foreach ($data as $row) {
            $rowData = array_values($row);
            $worksheet->fromArray($rowData, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        return self::writeToString($spreadsheet, $format);
    }

    /**
     * Generate Excel file with custom headers and rows
     *
     * @param  array<string>  $headers
     * @param  array<array<mixed>>  $rows  Array of rows (each row is indexed array)
     * @return string Binary content of the Excel file
     */
    public static function generateWithHeaders(array $headers, array $rows, string $format = 'xlsx'): string
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        // Add headers
        $worksheet->fromArray($headers, null, 'A1');

        // Add rows
        $rowNumber = 2;
        foreach ($rows as $row) {
            $worksheet->fromArray($row, null, 'A'.$rowNumber);
            $rowNumber++;
        }

        return self::writeToString($spreadsheet, $format);
    }

    /**
     * Generate empty Excel file
     *
     * @return string Binary content of the Excel file
     */
    public static function generateEmpty(string $format = 'xlsx'): string
    {
        $spreadsheet = new Spreadsheet;

        return self::writeToString($spreadsheet, $format);
    }

    /**
     * Generate Excel file with headers only (no data rows)
     *
     * @param  array<string>  $headers
     * @return string Binary content of the Excel file
     */
    public static function generateHeadersOnly(array $headers, string $format = 'xlsx'): string
    {
        $spreadsheet = new Spreadsheet;
        $worksheet = $spreadsheet->getActiveSheet();

        $worksheet->fromArray($headers, null, 'A1');

        return self::writeToString($spreadsheet, $format);
    }

    /**
     * Write spreadsheet to string
     *
     * @return string Binary content
     */
    private static function writeToString(Spreadsheet $spreadsheet, string $format): string
    {
        $writer = $format === 'xls' ? new Xls($spreadsheet) : new Xlsx($spreadsheet);

        // Use temporary file to write
        $tempPath = sys_get_temp_dir().'/'.uniqid('excel_test_', true).'.'.$format;

        try {
            $writer->save($tempPath);
            $content = file_get_contents($tempPath);

            if ($content === false) {
                throw new RuntimeException('Failed to read generated Excel file');
            }

            return $content;
        } finally {
            if (file_exists($tempPath)) {
                unlink($tempPath);
            }
        }
    }
}
