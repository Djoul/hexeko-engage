<?php

declare(strict_types=1);

namespace App\Services\AdminPanel;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class NavigationBuilder
{
    private array $config;

    public function __construct()
    {
        $this->config = config('admin-panel-navigation', []);
    }

    /**
     * Build the navigation structure for the current user
     */
    public function build(?string $activePillar = null, ?string $activeSection = null, ?string $activeRoute = null): array
    {
        $user = Auth::user();

        // Temporairement, on continue même sans utilisateur pour déboguer
        // if (! $user) {
        //     return [];
        // }

        $cacheKey = $user
            ? "admin_navigation_{$user->id}_{$activePillar}_{$activeSection}_{$activeRoute}"
            : "admin_navigation_guest_{$activePillar}_{$activeSection}_{$activeRoute}";

        return Cache::remember($cacheKey, $this->config['performance']['cache_ttl'] ?? 300, function () use ($activePillar, $activeSection, $activeRoute): array {
            $pillar = in_array($activePillar, [null, '', '0'], true) ? $this->getDefaultPillar() : $activePillar;

            $tree = $this->buildNavigationTree($pillar, $activeSection, $activeRoute);

            return [
                'pillars' => $this->buildPillars($pillar),
                'active_pillar' => $pillar,
                'active_section' => $activeSection,
                'tree' => $tree,
                'breadcrumbs' => $this->buildBreadcrumbs($pillar, $activeSection),
                'route_registry' => $this->buildRouteRegistry(),
            ];
        });
    }

    /**
     * Build the three main pillars navigation
     */
    private function buildPillars(?string $activePillar): array
    {
        $pillars = [];

        foreach ($this->config['pillars'] ?? [] as $key => $pillar) {
            if ($this->hasAccess()) {
                $pillars[] = [
                    'key' => $key,
                    'label' => $pillar['label'],
                    'icon' => $pillar['icon'] ?? null,
                    'route' => $this->generateRoute($pillar['route'] ?? null),
                    'active' => $key === $activePillar,
                ];
            }
        }

        return $pillars;
    }

    /**
     * Build breadcrumb navigation
     */
    private function buildBreadcrumbs(?string $pillar, ?string $section): array
    {
        $breadcrumbs = [
            [
                'label' => 'Admin Panel',
                'route' => route('admin.index'),
            ],
        ];

        if (! in_array($pillar, [null, '', '0'], true)) {
            $pillarConfig = $this->config['pillars'][$pillar] ?? null;

            if ($pillarConfig) {
                $breadcrumbs[] = [
                    'label' => $pillarConfig['label'],
                    'route' => $this->generateRoute($pillarConfig['route'] ?? null),
                ];

                if (! in_array($section, [null, '', '0'], true)) {
                    $sectionConfig = $pillarConfig['sections'][$section] ?? null;

                    if ($sectionConfig) {
                        $breadcrumbs[] = [
                            'label' => $sectionConfig['label'],
                            'route' => null, // Current page, no link
                        ];
                    }
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Check if user has access to a permission
     */
    private function hasAccess(): bool
    {
        // Temporarily bypass all permission checks to debug navigation
        return true;
        // Original code commented for debugging:
        /*
        if ($permission === null || $permission === '' || $permission === '0') {
            return true;
        }

        // Check for GOD role first
        $user = Auth::user();
        if ($user && $user->hasRole('GOD')) {
            return true;
        }

        // Check specific permission
        return $this->userPermissions && $this->userPermissions->contains($permission);
        */
    }

    /**
     * Generate route URL safely
     */
    private function generateRoute(?string $routeName): ?string
    {
        if (in_array($routeName, [null, '', '0'], true)) {
            return null;
        }

        if (Route::has($routeName)) {
            return route($routeName);
        }

        // If route doesn't exist yet, return placeholder
        return '#'.$routeName;
    }

    /**
     * Get navigation for a specific pillar
     */
    public function getPillarNavigation(string $pillar): array
    {
        $pillarConfig = $this->config['pillars'][$pillar] ?? [];

        if (empty($pillarConfig)) {
            return [];
        }

        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return [
            'pillar' => $pillar,
            'label' => $pillarConfig['label'],
            'sections' => $this->buildSectionNodes($pillar, $pillarConfig['sections'] ?? [], null, null),
        ];
    }

    /**
     * Get all accessible pillars for the current user
     */
    public function getAccessiblePillars(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $accessible = [];

        foreach ($this->config['pillars'] ?? [] as $key => $pillar) {
            if ($this->hasAccess()) {
                $accessible[] = $key;
            }
        }

        return $accessible;
    }

    /**
     * Clear navigation cache for a user
     */
    public function clearCache(?int $userId = null): void
    {
        $userId = $userId ?? Auth::id();

        if ($userId) {
            Cache::forget("admin_navigation_{$userId}_*");
        }
    }

    /**
     * Get the default pillar for a user
     */
    public function getDefaultPillar(): string
    {
        $accessible = $this->getAccessiblePillars();

        if ($accessible === []) {
            return '';
        }

        $default = $this->config['defaults']['pillar'] ?? 'dashboard';

        // Return default if accessible, otherwise first accessible
        return in_array($default, $accessible) ? $default : $accessible[0];
    }

    /**
     * Check if a route requires specific permissions
     */
    public function routeRequiresPermission(string $routeName): ?string
    {
        foreach ($this->config['pillars'] ?? [] as $pillar) {
            if (($pillar['route'] ?? '') === $routeName) {
                return $pillar['permission'] ?? null;
            }

            foreach ($pillar['sections'] ?? [] as $section) {
                if (($section['route'] ?? '') === $routeName) {
                    return $section['permission'] ?? null;
                }

                foreach ($section['subsections'] ?? [] as $subsection) {
                    if (($subsection['route'] ?? '') === $routeName) {
                        return $subsection['permission'] ?? null;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Build the nested navigation tree used by the sidebar
     */
    private function buildNavigationTree(string $pillar, ?string $activeSection, ?string $activeRoute): array
    {
        $tree = [];

        foreach ($this->config['pillars'] ?? [] as $pillarKey => $pillarConfig) {
            if (! $this->hasAccess()) {
                continue;
            }

            $sections = $this->buildSectionNodes(
                $pillarKey,
                $pillarConfig['sections'] ?? [],
                $activeSection,
                $activeRoute
            );

            $isActive = $pillarKey === $pillar
                || (($pillarConfig['route'] ?? null) !== null && ($pillarConfig['route'] === $activeRoute))
                || $this->containsActiveNode($sections);

            $tree[] = [
                'id' => $pillarKey,
                'label' => $pillarConfig['label'],
                'icon' => $pillarConfig['icon'] ?? null,
                'route_name' => $pillarConfig['route'] ?? null,
                'url' => $this->generateRoute($pillarConfig['route'] ?? null),
                'is_active' => $isActive,
                'children' => $sections,
            ];
        }

        return $tree;
    }

    /**
     * Build the second navigation level nodes (sections)
     */
    private function buildSectionNodes(string $pillarKey, array $sections, ?string $activeSection, ?string $activeRoute): array
    {
        $nodes = [];

        foreach ($sections as $sectionKey => $sectionConfig) {
            if (! $this->hasAccess()) {
                continue;
            }

            $routeName = $sectionConfig['route'] ?? null;
            $children = $this->buildSubsectionNodes($pillarKey, $sectionKey, $sectionConfig['subsections'] ?? [], $activeRoute);

            $isActive = $sectionKey === $activeSection
                || ($routeName !== null && $routeName === $activeRoute)
                || $this->containsActiveNode($children);

            $nodes[] = [
                'id' => $sectionKey,
                'pillar' => $pillarKey,
                'label' => $sectionConfig['label'],
                'icon' => $sectionConfig['icon'] ?? null,
                'route_name' => $routeName,
                'url' => $this->generateRoute($routeName),
                'is_active' => $isActive,
                'children' => $children,
            ];
        }

        return $nodes;
    }

    /**
     * Build the third navigation level nodes (subsections)
     */
    private function buildSubsectionNodes(string $pillarKey, string $sectionKey, array $subsections, ?string $activeRoute): array
    {
        $nodes = [];

        foreach ($subsections as $subKey => $subsectionConfig) {
            if (! $this->hasAccess()) {
                continue;
            }

            $routeName = $subsectionConfig['route'] ?? null;
            $isActive = $routeName !== null && $routeName === $activeRoute;

            $nodes[] = [
                'id' => sprintf('%s.%s.%s', $pillarKey, $sectionKey, $subKey),
                'pillar' => $pillarKey,
                'section' => $sectionKey,
                'subsection' => $subKey,
                'label' => $subsectionConfig['label'],
                'route_name' => $routeName,
                'url' => $this->generateRoute($routeName),
                'is_active' => $isActive,
            ];
        }

        return $nodes;
    }

    /**
     * Determine if any nested node is active
     */
    private function containsActiveNode(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if (($node['is_active'] ?? false) === true) {
                return true;
            }

            if (! empty($node['children']) && $this->containsActiveNode($node['children'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a registry of every available navigation route
     */
    private function buildRouteRegistry(): array
    {
        $registry = [];

        foreach ($this->config['pillars'] ?? [] as $pillarKey => $pillarConfig) {
            foreach ($pillarConfig['sections'] ?? [] as $sectionKey => $sectionConfig) {
                $sectionRoute = $sectionConfig['route'] ?? null;
                if ($sectionRoute) {
                    $registry[sprintf('%s.%s', $pillarKey, $sectionKey)] = $sectionRoute;
                }

                foreach ($sectionConfig['subsections'] ?? [] as $subKey => $subsectionConfig) {
                    $subRoute = $subsectionConfig['route'] ?? null;
                    if ($subRoute) {
                        $registry[sprintf('%s.%s.%s', $pillarKey, $sectionKey, $subKey)] = $subRoute;
                    }
                }
            }
        }

        return $registry;
    }
}
