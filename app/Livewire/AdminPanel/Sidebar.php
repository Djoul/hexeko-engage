<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel;

use App\Services\AdminPanel\NavigationBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Livewire\Component;

class Sidebar extends Component
{
    public string $activeSection = 'dashboard';

    public string $activeSubsection = '';

    public ?string $activeLeaf = null;

    public bool $isCollapsed = false;

    public array $breadcrumbs = [];

    public array $navigation = [];

    public array $navigationTree = [];

    public array $filteredTree = [];

    public array $routeRegistry = [];

    public string $search = '';

    protected NavigationBuilder $navigationBuilder;

    protected $listeners = [
        'navigationChanged' => 'handleNavigationChange',
        'refreshNavigation' => 'refreshNavigation',
        'sidebar-navigate' => 'handleSidebarNavigate',
    ];

    public function boot(NavigationBuilder $navigationBuilder): void
    {
        $this->navigationBuilder = $navigationBuilder;
    }

    public function mount(string $activeSection = '', string $activeSubsection = '', array $breadcrumbs = []): void
    {
        // Auto-detect active section from current URL if not provided
        if ($activeSection === '' || $activeSection === '0') {
            $this->detectActiveFromRoute();
        } else {
            $this->activeSection = $activeSection;
            $this->activeSubsection = $activeSubsection;
        }

        // Use default if still empty
        if ($this->activeSection === '' || $this->activeSection === '0') {
            $this->activeSection = $this->getDefaultSection();
        }

        $this->breadcrumbs = $breadcrumbs;

        // Load collapsed state from user preferences
        $this->isCollapsed = $this->getUserPreference('sidebar_collapsed', false);

        // Build navigation
        $this->refreshNavigationState();
    }

    public function render(): View
    {
        $this->refreshNavigationState();

        return view('livewire.admin-panel.navigation.sidebar', [
            'filteredTree' => $this->filteredTree,
        ]);
    }

    /**
     * Change active section (pillar)
     */
    public function changeSection(string $section): void
    {
        $this->activeSection = $section;
        $this->activeSubsection = '';
        $this->search = '';
        $this->refreshNavigationState();

        $this->dispatch('navigation-changed', section: $section);

        session(['admin_panel.current_pillar' => $section]);
    }

    /**
     * Change active subsection
     */
    public function changeSubsection(string $section, string $subsection): void
    {
        $this->activeSection = $section;
        $this->activeSubsection = $subsection;
        $this->refreshNavigationState();

        $this->dispatch('navigation-changed', section: $section, subsection: $subsection);

        session([
            'admin_panel.current_pillar' => $section,
            'admin_panel.current_section' => $subsection,
        ]);
    }

    /**
     * Toggle sidebar collapsed state
     */
    public function toggleCollapse(): void
    {
        $this->isCollapsed = ! $this->isCollapsed;

        // Save preference
        $this->saveUserPreference('sidebar_collapsed', $this->isCollapsed);

        // Emit event for layout adjustment
        $this->dispatch('sidebar-toggled', collapsed: $this->isCollapsed);
    }

    /**
     * Handle navigation change from other components
     */
    public function handleNavigationChange(string $section, ?string $subsection = null): void
    {
        $this->activeSection = $section;
        $this->activeSubsection = $subsection ?? '';
        $this->refreshNavigationState();
    }

    /**
     * Refresh navigation (e.g., after permission changes)
     */
    public function refreshNavigation(): void
    {
        // Clear cache
        $this->navigationBuilder->clearCache();

        $this->refreshNavigationState();
    }

    /**
     * Get user preference
     */
    private function getUserPreference(string $key, $default = null)
    {
        $user = Auth::user();

        if (! $user) {
            return $default;
        }

        $preferences = Cache::get("admin_panel_preferences_{$user->id}", []);

        return $preferences[$key] ?? $default;
    }

    /**
     * Save user preference
     */
    private function saveUserPreference(string $key, bool $value): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $preferences = Cache::get("admin_panel_preferences_{$user->id}", []);
        $preferences[$key] = $value;

        Cache::put("admin_panel_preferences_{$user->id}", $preferences, 86400); // 24 hours
    }

    /**
     * Get default section based on user permissions
     */
    private function getDefaultSection(): string
    {
        return $this->navigationBuilder->getDefaultPillar();
    }

    /**
     * Check if a section is active
     */
    public function isSectionActive(string $section): bool
    {
        return $this->activeSection === $section;
    }

    /**
     * Check if a subsection is active
     */
    public function isSubsectionActive(string $subsection): bool
    {
        return $this->activeSubsection === $subsection;
    }

    /**
     * Detect active pillar and section from current route
     */
    private function detectActiveFromRoute(): void
    {
        $currentRoute = request()->route()?->getName() ?? '';

        if (empty($currentRoute)) {
            return;
        }

        $config = config('admin-panel-navigation.pillars', []);

        // Check each pillar
        foreach ($config as $pillarKey => $pillar) {
            // Check pillar route
            if (($pillar['route'] ?? '') === $currentRoute) {
                $this->activeSection = $pillarKey;
                $this->activeSubsection = '';

                return;
            }

            // Check sections
            foreach ($pillar['sections'] ?? [] as $sectionKey => $section) {
                // Check section route
                if (($section['route'] ?? '') === $currentRoute) {
                    $this->activeSection = $pillarKey;
                    $this->activeSubsection = $sectionKey;

                    return;
                }

                // Check subsections
                foreach ($section['subsections'] ?? [] as $subsection) {
                    if (($subsection['route'] ?? '') === $currentRoute) {
                        $this->activeSection = $pillarKey;
                        $this->activeSubsection = $sectionKey;

                        return;
                    }
                }
            }
        }

        // Also check by URL pattern for translation routes
        $currentUrl = request()->path();
        if (str_contains($currentUrl, 'admin-panel/manager/translations')) {
            $this->activeSection = 'manager';
            $this->activeSubsection = 'translations';
        } elseif (str_contains($currentUrl, 'admin-panel/dashboard')) {
            $this->activeSection = 'dashboard';
        } elseif (str_contains($currentUrl, 'admin-panel/docs')) {
            $this->activeSection = 'docs';
        } elseif (str_contains($currentUrl, 'admin-panel/manager')) {
            $this->activeSection = 'manager';
        }
    }

    /**
     * Get sidebar width based on collapsed state
     */
    public function getSidebarWidth(): int
    {
        $config = config('admin-panel-navigation.ui');

        return $this->isCollapsed
            ? $config['sidebar_collapsed_width'] ?? 64
            : $config['sidebar_width'] ?? 256;
    }

    /**
     * Clear search
     */
    public function clearSearch(): void
    {
        $this->search = '';
        $this->updateFilteredTree();
    }

    /**
     * Navigate to a specific section
     */
    public function navigateTo(string $pillar, string $section, ?string $subsection = null): void
    {
        $this->activeSection = $pillar;
        $this->activeSubsection = $section;
        $this->activeLeaf = $subsection;
        $this->refreshNavigationState();

        $nodeKey = in_array($subsection, [null, '', '0'], true)
            ? sprintf('%s.%s', $pillar, $section)
            : sprintf('%s.%s.%s', $pillar, $section, $subsection);

        $routeName = $this->routeRegistry[$nodeKey] ?? null;

        if ($routeName && Route::has($routeName)) {
            $this->redirectRoute($routeName);

            return;
        }

        $url = $this->resolveNodeUrl($pillar, $section, $subsection);

        if ($url && ! str_starts_with($url, '#')) {
            $this->redirect($url);
        }
    }

    public function updatedSearch(string $value): void
    {
        $this->search = $value;
        $this->updateFilteredTree();
    }

    private function refreshNavigationState(): void
    {
        $this->navigation = $this->navigationBuilder->build(
            $this->activeSection,
            $this->activeSubsection,
            request()->route()?->getName()
        );

        $this->navigationTree = $this->navigation['tree'] ?? [];
        $this->routeRegistry = $this->navigation['route_registry'] ?? [];
        $this->breadcrumbs = $this->navigation['breadcrumbs'] ?? [];

        $this->updateFilteredTree();
    }

    private function updateFilteredTree(): void
    {
        $search = trim(mb_strtolower($this->search));

        if ($search === '') {
            $this->filteredTree = $this->navigationTree;

            return;
        }

        $this->filteredTree = $this->filterTree($this->navigationTree, $search);
    }

    private function filterTree(array $nodes, string $search): array
    {
        $filtered = [];

        foreach ($nodes as $node) {
            $label = mb_strtolower((string) ($node['label'] ?? ''));

            $matches = $label !== '' && str_contains($label, $search);
            $children = empty($node['children']) ? [] : $this->filterTree($node['children'], $search);

            if ($matches || $children !== []) {
                $filtered[] = array_merge($node, ['children' => $children]);
            }
        }

        return $filtered;
    }

    public function handleSidebarNavigate(string $action, string $pillar, ?string $section = null, ?string $subsection = null): void
    {
        if ($action === 'pillar') {
            $this->changeSection($pillar);

            return;
        }

        if ($action === 'section' && $section !== null) {
            $this->changeSubsection($pillar, $section);
            $this->navigateTo($pillar, $section);

            return;
        }

        if ($action === 'leaf' && $section !== null) {
            $this->navigateTo($pillar, $section, $subsection);
        }
    }

    private function resolveNodeUrl(string $pillar, string $section, ?string $subsection): ?string
    {
        foreach ($this->navigationTree as $node) {
            if (($node['id'] ?? null) !== $pillar) {
                continue;
            }

            foreach ($node['children'] ?? [] as $child) {
                if (($child['id'] ?? null) !== $section) {
                    continue;
                }

                if ($subsection === null) {
                    return $child['url'] ?? null;
                }

                foreach ($child['children'] ?? [] as $grandChild) {
                    if (($grandChild['subsection'] ?? null) === $subsection) {
                        return $grandChild['url'] ?? null;
                    }
                }
            }
        }

        return null;
    }
}
