<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AdminPanel Enabled
    |--------------------------------------------------------------------------
    |
    | This option controls whether the admin panel system is enabled.
    | When disabled, no admin panel routes or services will be loaded.
    |
    */
    'enabled' => env('DOCS_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure authentication requirements for admin panel access.
    |
    */
    'auth' => [
        'required' => env('DOCS_AUTH_REQUIRED', true),
        'public_routes' => [
            'admin.index',
            'admin.quickstart',
            'admin.search',
            'admin.auth.*',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Search Configuration
    |--------------------------------------------------------------------------
    |
    | Configure the search engine for admin panel.
    |
    */
    'search' => [
        'driver' => 'meilisearch',
        'host' => env('DOCS_MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => env('DOCS_MEILISEARCH_KEY', 'masterKey'),
        'index' => 'admin-panel',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    |
    | Configure caching for admin panel pages.
    |
    */
    'cache' => [
        'ttl' => 3600, // 1 hour
        'prefix' => 'docs_',
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configure API testing settings.
    |
    */
    'api' => [
        'base_url' => env('APP_URL').'/api/v1',
        'timeout' => 30,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Configure admin panel export options.
    |
    */
    'export' => [
        'pdf' => [
            'enabled' => true,
            'driver' => 'dompdf',
        ],
    ],
];
