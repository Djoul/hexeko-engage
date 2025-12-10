<?php

declare(strict_types=1);

return [
    'pdf' => [
        'cache_enabled' => env('INVOICE_PDF_CACHE_ENABLED', true),
        'cache_ttl_hours' => env('INVOICE_PDF_CACHE_TTL', 6),
        'storage_disk' => env('INVOICE_PDF_STORAGE', 's3'),
        'storage_path' => env('INVOICE_PDF_STORAGE_PATH', 'invoices/pdf'),
    ],

    'generation' => [
        'queue' => env('INVOICE_GENERATION_QUEUE', 'invoicing'),
        'retry_attempts' => env('INVOICE_GENERATION_RETRY_ATTEMPTS', 3),
        'retry_backoff' => env('INVOICE_GENERATION_RETRY_BACKOFF', 60),
    ],

    'export' => [
        'queue' => env('INVOICE_EXPORT_QUEUE', 'invoicing-export'),
        'filename_prefix' => env('INVOICE_EXPORT_FILENAME_PREFIX', 'invoices'),
    ],

    'emails' => [
        'queue' => env('INVOICE_EMAIL_QUEUE', 'emails'),
    ],

    'invoice_number' => [
        'pattern' => env('INVOICE_NUMBER_PATTERN', '{type}-{year}-{sequence}'),
        'sequence_padding' => (int) env('INVOICE_NUMBER_PADDING', 6),
        'type_mapping' => [],
    ],
];
