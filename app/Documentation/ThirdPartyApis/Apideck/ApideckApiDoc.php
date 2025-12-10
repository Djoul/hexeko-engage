<?php

declare(strict_types=1);

namespace App\Documentation\ThirdPartyApis\Apideck;

use App\Documentation\ThirdPartyApis\BaseApiDoc;

/**
 * Documentation for Apideck HRIS API
 * Unified API for HRIS integrations
 *
 * @see https://developers.apideck.com/apis/hris
 */
class ApideckApiDoc extends BaseApiDoc
{
    public static function getApiVersion(): string
    {
        return 'v1';
    }

    public static function getLastVerified(): string
    {
        return '2025-08-15';
    }

    public static function getProviderName(): string
    {
        return 'apideck';
    }

    /**
     * List all employees from HRIS system
     *
     * @return array<string, mixed>
     */
    public static function listEmployees(): array
    {
        return [
            'description' => 'Get a list of employees from the HRIS system',
            'endpoint' => 'GET /hris/employees',
            'documentation_url' => 'https://developers.apideck.com/apis/hris/reference#operation/employeesAll',
            'parameters' => [
                'raw' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Include raw response from downstream service',
                    'default' => false,
                ],
                'consumer_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Consumer ID to make request on behalf of',
                ],
                'app_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Application ID',
                ],
                'service_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Service ID of the downstream service',
                ],
                'cursor' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Cursor for pagination',
                ],
                'limit' => [
                    'type' => 'integer',
                    'required' => false,
                    'description' => 'Number of results to return',
                    'default' => 20,
                ],
                'filter' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Filter parameters',
                    'properties' => [
                        'company_id' => 'Filter by company ID',
                        'email' => 'Filter by email address',
                        'employment_status' => 'Filter by employment status',
                    ],
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'x-apideck-app-id' => '{app_id}',
                'x-apideck-service-id' => '{service_id}',
            ],
            'responses' => [
                '200' => [
                    'status_code' => 200,
                    'status' => 'OK',
                    'service' => 'bamboohr',
                    'resource' => 'employees',
                    'operation' => 'all',
                    'data' => [
                        [
                            'id' => '12345',
                            'social_security_number' => '123456789',
                            'first_name' => 'John',
                            'middle_name' => 'M',
                            'last_name' => 'Doe',
                            'display_name' => 'John Doe',
                            'preferred_name' => 'Johnny',
                            'initials' => 'JD',
                            'salutation' => 'Mr',
                            'title' => 'Software Engineer',
                            'marital_status' => 'single',
                            'partner' => null,
                            'division' => 'Engineering',
                            'division_id' => 'eng-001',
                            'department' => 'Product',
                            'department_id' => 'prod-001',
                            'department_name' => 'Product Development',
                            'team' => [
                                'id' => 'team-001',
                                'name' => 'Backend Team',
                            ],
                            'company_id' => 'company-001',
                            'company_name' => 'Tech Corp',
                            'employment_start_date' => '2020-01-15',
                            'employment_end_date' => null,
                            'leaving_reason' => null,
                            'employee_number' => 'EMP001',
                            'employment_status' => 'active',
                            'employment_role' => [
                                'type' => 'employee',
                                'sub_type' => 'full_time',
                            ],
                            'ethnicity' => null,
                            'manager' => [
                                'id' => '67890',
                                'name' => 'Jane Smith',
                                'first_name' => 'Jane',
                                'last_name' => 'Smith',
                                'email' => 'jane.smith@company.com',
                                'employment_status' => 'active',
                            ],
                            'direct_reports' => [],
                            'social_links' => [
                                [
                                    'url' => 'https://linkedin.com/in/johndoe',
                                    'type' => 'linkedin',
                                ],
                            ],
                            'bank_accounts' => [],
                            'date_of_birth' => '1990-01-01',
                            'place_of_birth' => 'New York',
                            'gender' => 'male',
                            'pronouns' => 'he/him',
                            'nationality' => 'US',
                            'languages' => ['en', 'es'],
                            'photo_url' => 'https://example.com/photo.jpg',
                            'timezone' => 'America/New_York',
                            'source' => 'bamboohr',
                            'source_id' => 'bamboo-12345',
                            'record_url' => 'https://example.bamboohr.com/employees/12345',
                            'jobs' => [
                                [
                                    'id' => 'job-001',
                                    'employee_id' => '12345',
                                    'title' => 'Software Engineer',
                                    'effective_date' => '2020-01-15',
                                    'compensation_rate' => 100000,
                                    'currency' => 'USD',
                                    'payment_unit' => 'year',
                                    'hired_at' => '2020-01-15',
                                    'is_primary' => true,
                                    'is_manager' => false,
                                    'status' => 'active',
                                    'location' => [
                                        'id' => 'loc-001',
                                        'name' => 'HQ Office',
                                        'address' => [
                                            'street_1' => '123 Main St',
                                            'city' => 'New York',
                                            'state' => 'NY',
                                            'postal_code' => '10001',
                                            'country' => 'US',
                                        ],
                                    ],
                                ],
                            ],
                            'compensations' => [],
                            'works_remote' => false,
                            'addresses' => [
                                [
                                    'id' => 'addr-001',
                                    'type' => 'home',
                                    'street_1' => '456 Oak Ave',
                                    'street_2' => 'Apt 2B',
                                    'city' => 'Brooklyn',
                                    'state' => 'NY',
                                    'postal_code' => '11201',
                                    'country' => 'US',
                                ],
                            ],
                            'phone_numbers' => [
                                [
                                    'id' => 'phone-001',
                                    'number' => '+1-555-123-4567',
                                    'type' => 'mobile',
                                ],
                            ],
                            'emails' => [
                                [
                                    'id' => 'email-001',
                                    'email' => 'john.doe@company.com',
                                    'type' => 'work',
                                ],
                                [
                                    'id' => 'email-002',
                                    'email' => 'john.doe@personal.com',
                                    'type' => 'personal',
                                ],
                            ],
                            'custom_fields' => [],
                            'custom_mappings' => null,
                            'updated_by' => 'system',
                            'created_by' => 'system',
                            'updated_at' => '2024-01-15T10:30:00Z',
                            'created_at' => '2020-01-15T08:00:00Z',
                            'deleted' => false,
                            'pass_through' => [],
                        ],
                    ],
                    'meta' => [
                        'items_on_page' => 1,
                        'cursors' => [
                            'previous' => null,
                            'current' => 'MTAz',
                            'next' => 'MTA0',
                        ],
                    ],
                    'links' => [
                        'previous' => null,
                        'current' => 'https://unify.apideck.com/hris/employees',
                        'next' => 'https://unify.apideck.com/hris/employees?cursor=MTA0',
                    ],
                ],
                '401' => [
                    'error' => 'Unauthorized',
                    'message' => 'Invalid or expired token',
                ],
                '404' => [
                    'error' => 'Not Found',
                    'message' => 'Consumer not found',
                ],
            ],
            'notes' => [
                'Unified response format across different HRIS providers',
                'Consumer ID determines which integration to use',
                'Supports pagination via cursor',
                'Custom fields vary by provider',
            ],
        ];
    }

    /**
     * Get a single employee by ID
     *
     * @return array<string, mixed>
     */
    public static function getEmployee(): array
    {
        return [
            'description' => 'Get details of a specific employee',
            'endpoint' => 'GET /hris/employees/{id}',
            'documentation_url' => 'https://developers.apideck.com/apis/hris/reference#operation/employeesOne',
            'parameters' => [
                'id' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Employee ID',
                ],
                'raw' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Include raw response from downstream service',
                    'default' => false,
                ],
                'consumer_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Consumer ID to make request on behalf of',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'x-apideck-app-id' => '{app_id}',
            ],
            'responses' => [
                '200' => 'Same structure as listEmployees single item',
                '404' => [
                    'error' => 'Not Found',
                    'message' => 'Employee not found',
                ],
            ],
        ];
    }

    /**
     * Create or update an employee
     *
     * @return array<string, mixed>
     */
    public static function upsertEmployee(): array
    {
        return [
            'description' => 'Create a new employee or update existing',
            'endpoint' => 'POST /hris/employees',
            'documentation_url' => 'https://developers.apideck.com/apis/hris/reference#operation/employeesAdd',
            'body' => [
                'id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Employee ID for update',
                ],
                'first_name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'First name',
                ],
                'last_name' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Last name',
                ],
                'email' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Work email address',
                ],
                'employment_status' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Employment status (active, inactive, terminated)',
                    'default' => 'active',
                ],
                'employment_start_date' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Start date in YYYY-MM-DD format',
                ],
                'date_of_birth' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Date of birth in YYYY-MM-DD format',
                ],
                'department_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Department ID',
                ],
                'manager_id' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Manager employee ID',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status_code' => 200,
                    'status' => 'OK',
                    'service' => 'bamboohr',
                    'resource' => 'employees',
                    'operation' => 'add',
                    'data' => [
                        'id' => '12345',
                    ],
                ],
                '400' => [
                    'error' => 'Bad Request',
                    'message' => 'Invalid employee data',
                ],
                '422' => [
                    'error' => 'Unprocessable Entity',
                    'message' => 'The given data was invalid',
                ],
            ],
            'notes' => [
                'Creates new employee if ID not provided',
                'Updates existing employee if ID provided',
                'Required fields vary by HRIS provider',
                'Some fields may be read-only in certain systems',
            ],
        ];
    }

    /**
     * Sync employee batch
     *
     * @return array<string, mixed>
     */
    public static function syncEmployees(): array
    {
        return [
            'description' => 'Sync a batch of employees',
            'endpoint' => 'POST /hris/employees/sync',
            'documentation_url' => 'https://developers.apideck.com/apis/hris/reference#operation/employeesSync',
            'body' => [
                'employees' => [
                    'type' => 'array',
                    'required' => true,
                    'description' => 'Array of employee objects to sync',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status_code' => 200,
                    'status' => 'OK',
                    'data' => [
                        'processed' => 50,
                        'created' => 10,
                        'updated' => 35,
                        'failed' => 5,
                        'errors' => [
                            [
                                'employee_id' => '123',
                                'error' => 'Invalid email format',
                            ],
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Batch sync for efficient updates',
                'Returns summary of processed records',
                'Failed records include error details',
            ],
        ];
    }

    /**
     * Get available integrations for a consumer
     *
     * @return array<string, mixed>
     */
    public static function getConnections(): array
    {
        return [
            'description' => 'Get available HRIS connections for a consumer',
            'endpoint' => 'GET /vault/connections',
            'documentation_url' => 'https://developers.apideck.com/apis/vault/reference#operation/connectionsAll',
            'parameters' => [
                'api' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'Filter by API (e.g., hris)',
                    'default' => 'hris',
                ],
                'configured' => [
                    'type' => 'boolean',
                    'required' => false,
                    'description' => 'Filter configured connections only',
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'x-apideck-app-id' => '{app_id}',
            ],
            'responses' => [
                '200' => [
                    'status_code' => 200,
                    'status' => 'OK',
                    'data' => [
                        [
                            'id' => 'bamboohr',
                            'service_id' => 'bamboohr',
                            'name' => 'BambooHR',
                            'tag_line' => 'HR software with heart',
                            'unified_api' => 'hris',
                            'state' => 'callable',
                            'integration_state' => 'configured',
                            'auth_type' => 'oauth2',
                            'oauth_grant_type' => 'authorization_code',
                            'status' => 'live',
                            'enabled' => true,
                            'website' => 'https://www.bamboohr.com',
                            'icon' => 'https://res.cloudinary.com/apideck/image/upload/v1565176142/catalog/bamboohr/icon128x128.png',
                            'logo' => 'https://res.cloudinary.com/apideck/image/upload/v1565176142/catalog/bamboohr/logo.png',
                            'authorize_url' => 'https://vault.apideck.com/authorize/bamboohr',
                            'revoke_url' => 'https://vault.apideck.com/revoke/bamboohr',
                            'settings' => [
                                'instance_url' => 'https://api.bamboohr.com/api/gateway.php',
                                'base_url' => 'https://api.bamboohr.com',
                            ],
                            'metadata' => [
                                'account' => [
                                    'id' => '12345',
                                    'name' => 'Tech Corp',
                                ],
                                'plan' => 'premium',
                            ],
                            'created_at' => '2020-09-19T12:18:37.071Z',
                            'updated_at' => '2020-09-19T12:18:37.071Z',
                        ],
                    ],
                ],
            ],
            'notes' => [
                'Shows which HRIS systems are connected',
                'State indicates if connection is ready to use',
                'Integration state shows configuration status',
            ],
        ];
    }

    /**
     * Create Vault session for user to configure integrations
     *
     * @return array<string, mixed>
     */
    public static function createVaultSession(): array
    {
        return [
            'description' => 'Create a Vault session for integration configuration',
            'endpoint' => 'POST /vault/sessions',
            'documentation_url' => 'https://developers.apideck.com/apis/vault/reference#operation/sessionsCreate',
            'body' => [
                'consumer_metadata' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Metadata about the consumer',
                    'properties' => [
                        'account_name' => 'Company name',
                        'user_name' => 'User name',
                        'email' => 'User email',
                        'image' => 'Profile image URL',
                    ],
                ],
                'redirect_uri' => [
                    'type' => 'string',
                    'required' => false,
                    'description' => 'URL to redirect after completion',
                ],
                'settings' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Session settings',
                    'properties' => [
                        'unified_apis' => 'Array of APIs to show (e.g., ["hris"])',
                        'hide_resource_settings' => 'Hide resource configuration',
                        'sandbox_mode' => 'Enable sandbox mode',
                        'isolation_mode' => 'Enable isolation mode',
                        'session_length' => 'Session length in seconds',
                        'show_logs' => 'Show logs in Vault',
                        'show_suggestions' => 'Show integration suggestions',
                        'show_sidebar' => 'Show sidebar navigation',
                        'auto_redirect' => 'Auto redirect on completion',
                    ],
                ],
                'theme' => [
                    'type' => 'object',
                    'required' => false,
                    'description' => 'Vault theme customization',
                    'properties' => [
                        'favicon' => 'Favicon URL',
                        'logo' => 'Logo URL',
                        'primary_color' => 'Primary color hex',
                        'sidepanel_background_color' => 'Sidepanel background color',
                        'sidepanel_text_color' => 'Sidepanel text color',
                        'vault_name' => 'Custom Vault name',
                        'privacy_url' => 'Privacy policy URL',
                        'terms_url' => 'Terms of service URL',
                    ],
                ],
            ],
            'headers' => [
                'Authorization' => 'Bearer {access_token}',
                'x-apideck-consumer-id' => '{consumer_id}',
                'x-apideck-app-id' => '{app_id}',
                'Content-Type' => 'application/json',
            ],
            'responses' => [
                '200' => [
                    'status_code' => 200,
                    'status' => 'OK',
                    'data' => [
                        'session_uri' => 'https://vault.apideck.com/session/eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
                        'session_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJjb25zdW1lcl9pZCI6InRlc3QtY29uc3VtZXItMTIzIiwiYXBwbGljYXRpb25faWQiOiI2ZTc3YzI4Yy04MzY1LTQyNjEtYjFjZS0xMTQwOGFmODFiODgiLCJpYXQiOjE2MjkyOTI2MjgsImV4cCI6MTYyOTI5NjIyOH0', // pragma: allowlist secret
                    ],
                ],
                '400' => [
                    'error' => 'Bad Request',
                    'message' => 'Invalid session configuration',
                ],
            ],
            'notes' => [
                'Session token expires after configured time',
                'Users can configure multiple integrations in one session',
                'Theme allows white-labeling the Vault UI',
                'Redirect URI is called after user completes configuration',
            ],
        ];
    }
}
