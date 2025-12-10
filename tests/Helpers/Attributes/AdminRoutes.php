<?php

namespace Tests\Helpers\Attributes;

/**
 * Admin Panel Route Constants for Testing
 */
class AdminRoutes
{
    // Existing protected routes that require authentication
    public const TEST_AUTH = '/admin-panel/test-auth';

    public const HOME = '/admin-panel/docs/home';

    public const QUICKSTART = '/admin-panel/quickstart';

    public const API_INDEX = '/admin-panel/api';

    // Future API routes to be implemented
    public const API_LOGIN = '/api/v1/admin/auth/login';

    public const API_REFRESH = '/api/v1/admin/auth/refresh';

    public const API_LOGOUT = '/api/v1/admin/auth/logout';

    public const API_VALIDATE = '/api/v1/admin/auth/validate';

    // Routes for testing various scenarios
    public const PROTECTED_ROUTES = [
        ['method' => 'GET', 'uri' => self::TEST_AUTH],
        ['method' => 'GET', 'uri' => self::HOME],
        ['method' => 'GET', 'uri' => self::QUICKSTART],
        ['method' => 'GET', 'uri' => self::API_INDEX],
    ];
}
