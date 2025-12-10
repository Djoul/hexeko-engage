<?php

declare(strict_types=1);

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class FallbackController extends Controller
{
    /**
     * Show under construction page for placeholder routes
     */
    public function underConstruction(string $feature = 'default'): View
    {
        $configs = $this->getFeatureConfig($feature);

        return view('admin-panel.under-construction', [
            'title' => $configs['title'],
            'description' => $configs['description'],
            'expectedDate' => $configs['expectedDate'] ?? null,
            'features' => $configs['features'] ?? [],
        ]);
    }

    /**
     * Get configuration for specific features
     */
    private function getFeatureConfig(string $feature): array
    {
        $configs = [
            'migrations' => [
                'title' => 'Migration Manager',
                'description' => 'Database migration management interface is being developed.',
                'expectedDate' => 'Q1 2025',
                'features' => [
                    'View migration status',
                    'Execute migrations',
                    'Rollback migrations',
                    'Migration history',
                    'Batch operations',
                ],
            ],
            'roles' => [
                'title' => 'Roles & Permissions Manager',
                'description' => 'Role-based access control management system.',
                'expectedDate' => 'Q1 2025',
                'features' => [
                    'Create and manage roles',
                    'Assign permissions',
                    'User role assignments',
                    'Permission matrix view',
                    'Audit trail',
                ],
            ],
            'audit' => [
                'title' => 'Audit Logs',
                'description' => 'System audit trail and activity monitoring.',
                'expectedDate' => 'Q1 2025',
                'features' => [
                    'View all system activities',
                    'Filter by user and action',
                    'Export audit reports',
                    'Real-time monitoring',
                    'Security alerts',
                ],
            ],
            'api' => [
                'title' => 'API Documentation',
                'description' => 'Interactive API documentation and testing interface.',
                'expectedDate' => 'Q1 2025',
                'features' => [
                    'Interactive API explorer',
                    'Request/response examples',
                    'Authentication guide',
                    'SDK downloads',
                    'Webhook documentation',
                ],
            ],
            'development' => [
                'title' => 'Development Guide',
                'description' => 'Comprehensive development documentation.',
                'expectedDate' => 'Q1 2025',
                'features' => [
                    'Architecture documentation',
                    'Coding standards',
                    'Testing guidelines',
                    'Deployment procedures',
                    'Best practices',
                ],
            ],
            'default' => [
                'title' => 'Feature Under Development',
                'description' => 'This feature is currently being built and will be available soon.',
                'expectedDate' => null,
                'features' => [],
            ],
        ];

        return $configs[$feature] ?? $configs['default'];
    }
}
