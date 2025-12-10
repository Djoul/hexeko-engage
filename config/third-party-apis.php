<?php

return [
    // Configuration globale
    'log_calls' => env('LOG_THIRD_PARTY_API_CALLS'),
    'save_responses' => env('SAVE_THIRD_PARTY_API_RESPONSES', env('APP_ENV') === 'local' || env('APP_ENV') === 'staging'),

    // Configuration par provider
    'providers' => [
        'amilon' => [
            'base_url' => env('AMILON_API_URL', 'https://api.amilon.com'),
            'client_id' => env('AMILON_CLIENT_ID'),
            'client_secret' => env('AMILON_CLIENT_SECRET'),
            'timeout' => env('AMILON_TIMEOUT', 30),
            'retry' => [
                'times' => env('AMILON_RETRY_TIMES', 3),
                'sleep' => env('AMILON_RETRY_SLEEP', 100),
            ],
        ],

        'stripe' => [
            'base_url' => env('STRIPE_API_URL', 'https://api.stripe.com/v1'),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
            'timeout' => env('STRIPE_TIMEOUT', 30),
            'retry' => [
                'times' => env('STRIPE_RETRY_TIMES', 3),
                'sleep' => env('STRIPE_RETRY_SLEEP', 100),
            ],
        ],

        'wellwo' => [
            'base_url' => env('WELLWO_API_URL', 'https://my.wellwo.net/api/v1'),
            'auth_token' => env('WELLWO_AUTH_TOKEN'),
            'timeout' => env('WELLWO_TIMEOUT', 30),
            'retry' => [
                'times' => env('WELLWO_RETRY_TIMES', 3),
                'sleep' => env('WELLWO_RETRY_DELAY', 100),
            ],
            'cache_ttl' => env('WELLWO_CACHE_TTL', 300),
        ],

        // Autres providers peuvent être ajoutés ici
    ],
];
