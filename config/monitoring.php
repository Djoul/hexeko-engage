<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | User Login Monitoring Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration allows you to monitor specific users' authentication
    | activities and receive Slack notifications when they log in.
    |
    */

    'user_login' => [
        /*
         * Enable or disable user login monitoring
         */
        'enabled' => env('MONITORING_USER_LOGIN_ENABLED', false),

        /*
         * Cognito IDs of users to monitor (comma-separated in .env)
         * Example: MONITORING_USER_LOGIN_USER_IDS="user-id-1,user-id-2,user-id-3"
         */
        'user_ids' => array_filter(
            explode(',', env('MONITORING_USER_LOGIN_USER_IDS', ''))
        ),

        /*
         * Slack channel where notifications will be sent
         */
        'slack_channel' => env('MONITORING_USER_LOGIN_SLACK_CHANNEL', 'up-engage-tech'),

        /*
         * Throttle period in minutes to prevent notification spam
         * Notifications will only be sent once per this period for each user
         */
        'throttle_minutes' => (int) env('MONITORING_USER_LOGIN_THROTTLE_MINUTES', 5),
    ],
];
