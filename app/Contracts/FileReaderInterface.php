<?php

declare(strict_types=1);

namespace App\Contracts;

interface FileReaderInterface
{
    /**
     * Read and parse file content into rows
     *
     * @return array{rows: array<int, array<string, mixed>>, error: string|null}
     */
    public function readAndValidate(string $filePath): array;

    /**
     * Get supported file extensions for this reader
     *
     * @return array<string>
     */
    public function getSupportedExtensions(): array;

    /**
     * Get MIME types supported by this reader
     *
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array;
}
