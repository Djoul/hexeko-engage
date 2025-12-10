<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CsvImportErrorsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $userName,
        public readonly int $totalRows,
        public readonly int $processedRows,
        public readonly int $failedRows,
        public readonly array $failedRowsDetails,
        public readonly ?float $totalDuration = null,
        public readonly ?string $financerName = null
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CSV Import Errors Report - '.$this->failedRows.' errors found',
        );
    }

    public function content(): Content
    {
        // Group errors by type for summary
        $errorSummary = [];
        foreach ($this->failedRowsDetails as $detail) {
            $errorMessage = $detail['error'] ?? 'Unknown error';
            if (! isset($errorSummary[$errorMessage])) {
                $errorSummary[$errorMessage] = 0;
            }
            $errorSummary[$errorMessage]++;
        }

        return new Content(
            view: 'emails.csv-import-errors',
            with: [
                'importId' => $this->importId,
                'userName' => $this->userName,
                'totalRows' => $this->totalRows,
                'processedRows' => $this->processedRows,
                'failedRows' => $this->failedRows,
                'failedRowsDetails' => $this->failedRowsDetails,
                'errorSummary' => $errorSummary,
                'totalDuration' => $this->totalDuration,
                'financerName' => $this->financerName,
                'importDate' => now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function attachments(): array
    {
        // Generate CSV file with errors as attachment
        $csvContent = $this->generateErrorsCsv();

        if ($csvContent !== '') {
            $fileName = 'import_errors_'.$this->importId.'.csv';
            $path = storage_path('app/temp/'.$fileName);

            // Ensure temp directory exists
            if (! is_dir(storage_path('app/temp'))) {
                mkdir(storage_path('app/temp'), 0755, true);
            }

            file_put_contents($path, $csvContent);

            return [
                Attachment::fromPath($path)
                    ->as($fileName)
                    ->withMime('text/csv'),
            ];
        }

        return [];
    }

    private function generateErrorsCsv(): string
    {
        if ($this->failedRowsDetails === []) {
            return '';
        }

        $csv = "Email,First Name,Last Name,Phone,External ID,Error Message\n";

        foreach ($this->failedRowsDetails as $detail) {
            $row = $detail['row'] ?? [];
            $error = $detail['error'] ?? 'Unknown error';

            $csv .= '"'.($row['email'] ?? '').'",';
            $csv .= '"'.($row['first_name'] ?? '').'",';
            $csv .= '"'.($row['last_name'] ?? '').'",';
            $csv .= '"'.($row['phone'] ?? '').'",';
            $csv .= '"'.($row['external_id'] ?? '').'",';
            $csv .= '"'.str_replace('"', '""', $error).'"'."\n";
        }

        return $csv;
    }
}
