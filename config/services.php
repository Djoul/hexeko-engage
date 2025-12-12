<?php

use App\Enums\Languages;

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL', '#general'),
            'username' => env('SLACK_BOT_USERNAME'),
            'icon_emoji' => env('SLACK_BOT_ICON_EMOJI'),
            'timeout' => env('SLACK_TIMEOUT', 30),
            'retry_times' => env('SLACK_RETRY_TIMES', 3),
            'retry_delay' => env('SLACK_RETRY_DELAY', 100),
        ],
        'channels' => [
            'alerts' => env('SLACK_CHANNEL_ALERTS', '#alerts'),
            'errors' => env('SLACK_CHANNEL_ERRORS', '#errors'),
            'deployments' => env('SLACK_CHANNEL_DEPLOYMENTS', '#deployments'),
            'monitoring' => env('SLACK_CHANNEL_MONITORING', '#monitoring'),
        ],
        'webhooks' => [
            'default' => env('SLACK_WEBHOOK_URL'),
        ],
        'cognito_alerts_webhook' => env('SLACK_COGNITO_ALERTS_WEBHOOK'),
    ],
    'aws' => [
        'access_key_id' => env('AWS_ACCESS_KEY_ID'),
        'secret_access_key' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_REGION', 'eu-west-3'),
    ],
    'cognito' => [
        'region' => env('AWS_COGNITO_REGION', 'eu-west-3'),
        'user_pool_id' => env('AWS_COGNITO_USER_POOL_ID'),
        'client_id' => env('AWS_COGNITO_CLIENT_ID'),
        'client_secret' => env('AWS_COGNITO_CLIENT_SECRET'),
        'webhook_secret' => env('COGNITO_WEBHOOK_SECRET'),
        'hmac_strict_mode' => env('COGNITO_HMAC_STRICT_MODE', true),
        'timeout' => env('AWS_COGNITO_TIMEOUT', 10),
        'connect_timeout' => env('AWS_COGNITO_CONNECT_TIMEOUT', 5),
    ],
    'apideck' => [
        'key' => env('APIDECK_API_KEY'),
        'app_id' => env('APIDECK_APP_ID'),
        'consumer_id' => env('APIDECK_CONSUMER_ID', null),
        'base_url' => env('APIDECK_BASE_URL', 'https://unify.apideck.com'),
        //        'service_id' => env('APIDECK_SERVICE_ID', 'bamboohr'),
    ],

    'vault' => [
        'session_duration' => env('APIDECK_VAULT_SESSION_DURATION', 3600),
        'allowed_services' => ['bamboohr', 'personio', 'workday', 'hibob', 'namely', 'sage-hr', 'adp-workforce-now', 'factorialhr', 'officient-io', 'breathehr', 'cascade-hr', 'freshteam', 'folks-hr', 'gusto', 'lucca-hr', 'loket-nl', 'microsoft-dynamics-hr', 'nmbrs', 'payfit', 'paylocity', 'rippling', 'sdworx', 'sympa', 'zenefits', 'google-workspace'],
        'default_unified_apis' => ['hris'],
        'default_service_id' => env('APIDECK_DEFAULT_SERVICE_ID', null),
        'rate_limit' => [
            'max_attempts' => env('VAULT_RATE_LIMIT_MAX_ATTEMPTS', 10),
            'decay_minutes' => env('VAULT_RATE_LIMIT_DECAY_MINUTES', 60),
        ],
    ],

    'amilon' => [
        'contrat_id' => env('AMILON_CONTRAT_ID', '123-456-789'),
        'client_id' => env('AMILON_CLIENT_ID'),
        'client_secret' => env('AMILON_CLIENT_SECRET'),
        'username' => env('AMILON_USERNAME'),
        'password' => env('AMILON_PASSWORD'),
        'token_url' => env('AMILON_TOKEN_URL', 'https://b2bstg-sso.amilon.eu/connect/token'),
        'api_url' => env('AMILON_API_URL', 'https://b2bstg-webapi.amilon.eu'),
        'enabled_countries' => [
            // 'GB',
            'FR',
            // 'ES',
            'PT',
        ],
        'available_amounts' => [
            10.0,
            20.0,
            30.0,
            40.0,
            50.0,
            75.0,
            100.0,
            200.0,
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret_key' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'webhook_secret_cli' => env('STRIPE_WEBHOOK_SECRET_CLI'),
    ],

    'wellwo' => [
        'api_url' => env('WELLWO_API_URL', 'https://my.wellwo.net/api/v1'),
        'auth_token' => env('WELLWO_AUTH_TOKEN'),
        'timeout' => env('WELLWO_TIMEOUT', 30),
        'retry_times' => env('WELLWO_RETRY_TIMES', 3),
        'retry_delay' => env('WELLWO_RETRY_DELAY', 100),
        'cache_ttl' => env('WELLWO_CACHE_TTL', 300),
        'filter_by_language' => env('WELLWO_FILTER_BY_LANGUAGE', false),
        'supported_languages' => ['es', 'en', 'fr', 'it', 'pt', 'ca', 'mx'],
        'default_language' => 'en',
        'language_mapping' => [
            Languages::SPANISH => 'es',
            Languages::SPANISH_ARGENTINA => 'es',
            Languages::SPANISH_COLOMBIA => 'es',
            Languages::SPANISH_MEXICO => 'mx',
            Languages::ENGLISH => 'en',
            Languages::FRENCH => 'fr',
            Languages::FRENCH_BELGIUM => 'fr',
            Languages::FRENCH_CANADA => 'fr',
            Languages::FRENCH_SWITZERLAND => 'fr',
            Languages::ITALIAN => 'it',
            Languages::PORTUGUESE => 'pt',
            Languages::PORTUGUESE_BRAZIL => 'pt',
            // All other Languages enum values will use default_language
        ],
    ],

    'smsmode' => [
        'api_key' => env('SMSMODE_API_KEY'),
        'api_url' => env('SMSMODE_API_URL', 'https://api.smsmode.com'),
        'sender' => env('SMSMODE_SENDER', 'UpPlus+'),
    ],
];
