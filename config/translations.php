<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Translation Protection Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the multi-layered translation protection system
    | that ensures consistency across all environments.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Auto-Sync Configuration
    |--------------------------------------------------------------------------
    |
    | Controls automatic synchronization of translation files after
    | database migrations complete.
    |
    */
    'auto_sync_local' => env('TRANSLATIONS_AUTO_SYNC_LOCAL', false),
    'allow_local_editing' => env('TRANSLATIONS_ALLOW_LOCAL_EDITING', false),

    /*
    |--------------------------------------------------------------------------
    | Reconciliation Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for periodic reconciliation that catches any missed
    | translation files from failed deployments.
    |
    */
    'reconciliation' => [
        'enabled' => env('TRANSLATIONS_RECONCILE_ENABLED', true),
        'cron' => env('TRANSLATIONS_RECONCILE_CRON', '0 * * * *'), // Hourly by default
        'auto_reconcile_after_seed' => env('TRANSLATIONS_AUTO_RECONCILE_AFTER_SEED', true),
        'throttle_seconds' => env('TRANSLATIONS_RECONCILE_THROTTLE_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manifest Configuration
    |--------------------------------------------------------------------------
    |
    | Controls whether translation files must be validated against
    | a manifest before being applied in staging/production.
    |
    */
    'manifest_required' => env('TRANSLATIONS_MANIFEST_REQUIRED', true),

    /*
    |--------------------------------------------------------------------------
    | S3 Storage Configuration
    |--------------------------------------------------------------------------
    |
    | Disk configurations for translation file storage.
    |
    */
    's3_disk' => env('TRANSLATIONS_S3_DISK', 'translations-s3'),
    's3_disk_local' => env('TRANSLATIONS_S3_DISK_LOCAL', 'translations-s3-local'),
];
