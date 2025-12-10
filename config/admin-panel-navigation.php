<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Admin Panel Three-Pillar Navigation Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration defines the three-pillar navigation structure for the
    | admin panel: Dashboard (monitoring), Manager (administration), and
    | Documentation (organized content).
    |
    */

    'pillars' => [
        'dashboard' => [
            'label' => 'Dashboard',
            'icon' => 'heroicon-o-chart-bar',
            'route' => 'admin.dashboard.index',
            'permission' => 'admin.dashboard.view',
            'sections' => [
                'overview' => [
                    'label' => 'Overview',
                    'route' => 'admin.dashboard.index',
                    'icon' => 'heroicon-o-home',
                ],
                'metrics' => [
                    'label' => 'Metrics',
                    'route' => 'admin.dashboard.metrics',
                    'icon' => 'heroicon-o-chart-pie',
                ],
                'health' => [
                    'label' => 'System Health',
                    'route' => 'admin.dashboard.health',
                    'icon' => 'heroicon-o-shield-check',
                ],
                'queue' => [
                    'label' => 'Queue Status',
                    'route' => 'admin.dashboard.queue',
                    'icon' => 'heroicon-o-queue-list',
                ],
                'services' => [
                    'label' => 'Service Status',
                    'route' => 'admin.dashboard.services',
                    'icon' => 'heroicon-o-server',
                ],
            ],
        ],

        'manager' => [
            'label' => 'Manager',
            'icon' => 'heroicon-o-cog',
            'route' => 'admin.manager.index',
            'permission' => 'admin.manager.view',
            'sections' => [
                'translations' => [
                    'label' => 'Traductions',
                    'route' => 'admin.manager.translations.index',
                    'icon' => 'heroicon-o-language',
                    'subsections' => [
                        'overview' => [
                            'label' => 'Overview',
                            'route' => 'admin.manager.translations.index',
                        ],
                        'editor' => [
                            'label' => 'Translation Editor',
                            'route' => 'admin.manager.translations.editor',
                        ],
                        'migrations' => [
                            'label' => 'Migration Manager',
                            'route' => 'admin.manager.translations.migrations',
                        ],
                    ],
                ],
                'migrations' => [
                    'label' => 'Migration Manager',
                    'route' => 'admin.manager.migrations',
                    'icon' => 'heroicon-o-arrow-path',
                    'permission' => 'admin.migrations.manage',
                ],
                'roles' => [
                    'label' => 'Roles & Permissions',
                    'route' => 'admin.manager.roles',
                    'icon' => 'heroicon-o-user-group',
                    'permission' => 'admin.roles.manage',
                    'subsections' => [
                        'roles' => [
                            'label' => 'Roles',
                            'route' => 'admin.under-construction',
                        ],
                        'permissions' => [
                            'label' => 'Permissions',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
                'audit' => [
                    'label' => 'Audit Logs',
                    'route' => 'admin.manager.audit',
                    'icon' => 'heroicon-o-document-text',
                    'permission' => 'admin.audit.view',
                ],
            ],
        ],

        'docs' => [
            'label' => 'Documentation',
            'icon' => 'heroicon-o-book-open',
            'route' => 'admin.docs.index',
            'permission' => 'admin.docs.view',
            'sections' => [
                'overview' => [
                    'label' => 'Overview',
                    'route' => 'admin.docs.home',
                    'icon' => 'heroicon-o-home',
                ],
                'getting-started' => [
                    'label' => 'Getting Started',
                    'route' => 'admin.docs.getting-started',
                    'icon' => 'heroicon-o-academic-cap',
                    'subsections' => [
                        'installation' => [
                            'label' => 'Installation',
                            'route' => 'admin.docs.installation',
                        ],
                        'quickstart' => [
                            'label' => 'Quick Start Guide',
                            'route' => 'admin.docs.quick-start',
                        ],
                        'websocket-demo' => [
                            'label' => 'WebSocket Demo',
                            'route' => 'admin.websocket-demo',
                        ],
                        'authentication' => [
                            'label' => 'Authentication',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
                'api' => [
                    'label' => 'API Reference',
                    'route' => 'admin.docs.api',
                    'icon' => 'heroicon-o-code-bracket',
                    'subsections' => [
                        'authentication' => [
                            'label' => 'Authentication',
                            'route' => 'admin.under-construction',
                        ],
                        'users' => [
                            'label' => 'Users',
                            'route' => 'admin.under-construction',
                        ],
                        'teams' => [
                            'label' => 'Teams',
                            'route' => 'admin.under-construction',
                        ],
                        'orders' => [
                            'label' => 'Orders',
                            'route' => 'admin.under-construction',
                        ],
                        'payments' => [
                            'label' => 'Payments',
                            'route' => 'admin.under-construction',
                        ],
                        'webhooks' => [
                            'label' => 'Webhooks',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
                'development' => [
                    'label' => 'Development Guide',
                    'route' => 'admin.development',
                    'icon' => 'heroicon-o-command-line',
                    'subsections' => [
                        'backend' => [
                            'label' => 'Backend',
                            'route' => 'admin.under-construction',
                        ],
                        'frontend' => [
                            'label' => 'Frontend',
                            'route' => 'admin.under-construction',
                        ],
                        'testing' => [
                            'label' => 'Testing',
                            'route' => 'admin.testing',
                        ],
                        'make-commands' => [
                            'label' => 'Make Commands',
                            'route' => 'admin.make-commands',
                        ],
                        'docker' => [
                            'label' => 'Docker Setup',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
                'integrations' => [
                    'label' => 'Integration Guide',
                    'route' => 'admin.docs.integrations',
                    'icon' => 'heroicon-o-puzzle-piece',
                    'subsections' => [
                        'aws-cognito' => [
                            'label' => 'AWS Cognito',
                            'route' => 'admin.under-construction',
                        ],
                        'onesignal' => [
                            'label' => 'OneSignal',
                            'route' => 'admin.under-construction',
                        ],
                        's3' => [
                            'label' => 'AWS S3',
                            'route' => 'admin.under-construction',
                        ],
                        'redis' => [
                            'label' => 'Redis',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
                'reference' => [
                    'label' => 'Reference',
                    'route' => 'admin.docs.reference',
                    'icon' => 'heroicon-o-document-duplicate',
                    'subsections' => [
                        'env-variables' => [
                            'label' => 'Environment Variables',
                            'route' => 'admin.under-construction',
                        ],
                        'database-schema' => [
                            'label' => 'Database Schema',
                            'route' => 'admin.under-construction',
                        ],
                        'error-codes' => [
                            'label' => 'Error Codes',
                            'route' => 'admin.under-construction',
                        ],
                        'permissions' => [
                            'label' => 'Permissions',
                            'route' => 'admin.under-construction',
                        ],
                    ],
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */

    'defaults' => [
        'pillar' => 'dashboard',
        'section' => 'overview',
        'theme' => 'light',
        'sidebar_collapsed' => false,
        'refresh_interval' => 30, // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Real-time Update Settings
    |--------------------------------------------------------------------------
    */

    'realtime' => [
        'enabled' => env('ADMIN_PANEL_REALTIME', true),
        'polling_interval' => 5000, // milliseconds
        'websocket' => [
            'enabled' => env('ADMIN_PANEL_WEBSOCKET', false),
            'channel' => 'admin-panel-updates',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Settings
    |--------------------------------------------------------------------------
    */

    'performance' => [
        'cache_ttl' => 300, // 5 minutes
        'max_metrics_age' => 86400, // 24 hours
        'dashboard_refresh' => 30, // seconds
        'lazy_load_docs' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    */

    'security' => [
        'require_god_role' => env('ADMIN_PANEL_REQUIRE_GOD', true),
        'audit_enabled' => true,
        'session_timeout' => 3600, // 1 hour
        'ip_whitelist' => env('ADMIN_PANEL_IP_WHITELIST', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | UI Settings
    |--------------------------------------------------------------------------
    */

    'ui' => [
        'show_breadcrumbs' => true,
        'enable_search' => true,
        'enable_notifications' => true,
        'sidebar_width' => 256, // pixels
        'sidebar_collapsed_width' => 64, // pixels
    ],
];
