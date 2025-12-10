<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 's3'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET', 'hexeko-upengage-dev-main'), // Default bucket for production
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true,
            'report' => true,
            'options' => [
                // Disable ACL for S3 uploads to avoid BucketOwnerEnforced errors
                'ACL' => 'bucket-owner-full-control',
            ],
        ],

        's3-local' => [
            'driver' => 'minio', // Use our custom MinIO driver
            'key' => env('MINIO_ACCESS_KEY_ID', 'minio'),
            'secret' => env('MINIO_SECRET_ACCESS_KEY', 'minio123'),
            'region' => 'us-east-1', // MinIO doesn't use regions but S3 driver requires it
            'bucket' => env('MINIO_BUCKET', 'upengage'),
            'url' => env('MINIO_URL', 'http://localhost:9100/upengage'),
            'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'), // Internal endpoint for Docker
            'external_endpoint' => env('MINIO_EXTERNAL_HOST', 'http://localhost:9100'), // External endpoint for browser
            'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'report' => false,
        ],

        'translations-s3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('TRANSLATIONS_BUCKET', 'hexeko-upengage-localization-repository'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => true, // Changed to true to surface S3 errors
            'report' => true, // Changed to true for better error reporting
            'options' => [
                // Disable ACL for S3 uploads to avoid BucketOwnerEnforced errors
                'ACL' => 'bucket-owner-full-control',
            ],
        ],

        'translations-s3-local' => [
            'driver' => 'minio',
            'key' => env('MINIO_ACCESS_KEY_ID', 'minio'),
            'secret' => env('MINIO_SECRET_ACCESS_KEY', 'minio123'),
            'region' => 'us-east-1',
            'bucket' => env('TRANSLATIONS_BUCKET_LOCAL', env('MINIO_SECOND_BUCKET', 'localization-repository')),
            'url' => env('MINIO_EXTERNAL_HOST', 'http://localhost:9100').'/'.env('TRANSLATIONS_BUCKET_LOCAL', env('MINIO_SECOND_BUCKET', 'localization-repository')),
            'endpoint' => env('MINIO_ENDPOINT', 'http://minio:9000'),
            'external_endpoint' => env('MINIO_EXTERNAL_HOST', 'http://localhost:9100'),
            'use_path_style_endpoint' => env('S3_USE_PATH_STYLE_ENDPOINT', true),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
