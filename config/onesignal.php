<?php

return [
    /*
    |--------------------------------------------------------------------------
    | One Signal App Id
    |--------------------------------------------------------------------------
    |
    |
    */
    'app_id' => env('ONESIGNAL_APP_ID'),

    /*
    |--------------------------------------------------------------------------
    | Rest API Key
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    'rest_api_url' => env('ONESIGNAL_REST_API_URL', 'https://api.onesignal.com'),
    'rest_api_key' => env('ONESIGNAL_REST_API_KEY'),
    'user_auth_key' => env('ONESIGNAL_USER_AUTH_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Guzzle Timeout
    |--------------------------------------------------------------------------
    |
    |
    |
    */
    'guzzle_client_timeout' => env('ONESIGNAL_GUZZLE_CLIENT_TIMEOUT', 0),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret key for validating OneSignal webhook signatures
    |
    */
    'webhook_secret' => env('ONESIGNAL_WEBHOOK_SECRET'),
];
