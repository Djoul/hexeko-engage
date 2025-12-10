<?php

declare(strict_types=1);

namespace App\Services\FileReaders;

use App\Contracts\FileReaderInterface;
use InvalidArgumentException;

final readonly class FileReaderFactory
{
    /**
     * @var array<class-string<FileReaderInterface>>
     */
    private const READERS = [
        CsvFileReader::class,
        ExcelFileReader::class,
    ];

    /**
     * Create appropriate file reader based on file extension or MIME type
     */
    public function createFromFile(string $filePath, ?string $mimeType = null): FileReaderInterface
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Try to find reader by extension first
        $reader = $this->findReaderByExtension($extension);

        // Fallback to MIME type if extension didn't match
        if (! $reader instanceof FileReaderInterface && $mimeType !== null) {
            $reader = $this->findReaderByMimeType($mimeType);
        }

        if (! $reader instanceof FileReaderInterface) {
            throw new InvalidArgumentException(
                sprintf('Unsupported file format: %s (MIME: %s)', $extension, $mimeType ?? 'unknown')
            );
        }

        return $reader;
    }

    /**
     * Find appropriate reader by file extension
     */
    private function findReaderByExtension(string $extension): ?FileReaderInterface
    {
        foreach (self::READERS as $readerClass) {
            /** @var FileReaderInterface $reader */
            $reader = new $readerClass;

            if (in_array($extension, $reader->getSupportedExtensions(), true)) {
                return $reader;
            }
        }

        return null;
    }

    /**
     * Find appropriate reader by MIME type
     */
    private function findReaderByMimeType(string $mimeType): ?FileReaderInterface
    {
        foreach (self::READERS as $readerClass) {
            /** @var FileReaderInterface $reader */
            $reader = new $readerClass;

            if (in_array($mimeType, $reader->getSupportedMimeTypes(), true)) {
                return $reader;
            }
        }

        return null;
    }

    /**
     * Get all supported extensions across all readers
     *
     * @return array<string>
     */
    public function getSupportedExtensions(): array
    {
        $extensions = [];

        foreach (self::READERS as $readerClass) {
            /** @var FileReaderInterface $reader */
            $reader = new $readerClass;
            $extensions = [...$extensions, ...$reader->getSupportedExtensions()];
        }

        return array_unique($extensions);
    }

    /**
     * Get all supported MIME types across all readers
     *
     * @return array<string>
     */
    public function getSupportedMimeTypes(): array
    {
        $mimeTypes = [];

        foreach (self::READERS as $readerClass) {
            /** @var FileReaderInterface $reader */
            $reader = new $readerClass;
            $mimeTypes = [...$mimeTypes, ...$reader->getSupportedMimeTypes()];
        }

        return array_unique($mimeTypes);
    }
}
