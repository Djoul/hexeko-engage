<?php

declare(strict_types=1);

namespace App\Livewire\AdminPanel;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $searchQuery = '';

    /** @var array<int, array{title: string, description: string, url: string, category: string, content?: string}> */
    public array $results = [];

    public bool $showResults = false;

    public bool $isSearching = false;

    public int $selectedIndex = -1;

    public string $filterCategory = '';

    /** @var array<string> */
    public array $recentSearches = [];

    protected $listeners = [
        'globalSearchOpen' => 'openSearch',
        'globalSearchClose' => 'closeSearch',
    ];

    public function mount(): void
    {
        $this->loadRecentSearches();
    }

    public function render(): View
    {
        return view('livewire.admin-panel.global-search');
    }

    public function updatedSearchQuery(): void
    {
        if (strlen($this->searchQuery) >= 2) {
            $this->performSearch();
        } else {
            $this->clearResults();
        }
    }

    public function performSearch(): void
    {
        if (empty($this->searchQuery)) {
            $this->clearResults();

            return;
        }

        $this->isSearching = true;

        // Get searchable content
        $searchableContent = $this->getSearchableContent();

        // Filter by category if specified
        if ($this->filterCategory !== '' && $this->filterCategory !== '0') {
            $searchableContent = $searchableContent->filter(function (array $item): bool {
                return $item['category'] === $this->filterCategory;
            });
        }

        // Search through content
        $query = Str::lower($this->searchQuery);
        $results = $searchableContent->filter(function (array $item) use ($query) {
            $searchableText = Str::lower($item['title'].' '.$item['description'].' '.($item['content'] ?? ''));

            return Str::contains($searchableText, $query);
        })->map(function (array $item) use ($query): array {
            // Calculate relevance score
            $titleScore = $this->calculateRelevance($item['title'], $query) * 3;
            $descriptionScore = $this->calculateRelevance($item['description'], $query) * 2;
            $contentScore = $this->calculateRelevance($item['content'] ?? '', $query);

            $item['score'] = $titleScore + $descriptionScore + $contentScore;

            // Highlight search terms
            $item['title'] = $this->highlightSearchTerms($item['title'], $query);
            $item['description'] = $this->highlightSearchTerms($item['description'], $query);

            return $item;
        })->sortByDesc('score')->take(20)->values();

        $this->results = $results->toArray();
        $this->showResults = true;
        $this->isSearching = false;
        $this->selectedIndex = -1;

        // Save to recent searches
        $this->saveRecentSearch($this->searchQuery);
    }

    public function clearSearch(): void
    {
        $this->searchQuery = '';
        $this->clearResults();
    }

    public function clearResults(): void
    {
        $this->results = [];
        $this->showResults = false;
        $this->selectedIndex = -1;
    }

    public function navigateToResult(int $index): void
    {
        if (isset($this->results[$index])) {
            $this->redirect($this->results[$index]['url']);
        }
    }

    public function selectNext(): void
    {
        if (count($this->results) > 0) {
            $this->selectedIndex = min($this->selectedIndex + 1, count($this->results) - 1);
        }
    }

    public function selectPrevious(): void
    {
        $this->selectedIndex = max($this->selectedIndex - 1, -1);
    }

    public function selectResult(): void
    {
        if ($this->selectedIndex >= 0 && isset($this->results[$this->selectedIndex])) {
            $this->navigateToResult($this->selectedIndex);
        }
    }

    public function handleEscape(): void
    {
        $this->showResults = false;
        $this->selectedIndex = -1;
    }

    public function showRecentSearches(): void
    {
        if (empty($this->searchQuery) && $this->recentSearches !== []) {
            $this->showResults = true;
            $this->results = array_map(function ($search): array {
                return [
                    'title' => $search,
                    'description' => 'Recent search',
                    'url' => '#',
                    'category' => 'recent',
                ];
            }, $this->recentSearches);
        }
    }

    public function searchFromRecent(string $query): void
    {
        $this->searchQuery = $query;
        $this->performSearch();
    }

    private function getSearchableContent(): Collection
    {
        return Cache::remember('searchable_content', 3600, function (): Collection {
            $content = collect();

            // Documentation pages
            $content->push([
                'title' => 'Quick Start Guide',
                'description' => 'Get up and running with the UpEngage API in minutes',
                'url' => '/admin-panel/quickstart',
                'category' => 'getting-started',
                'content' => 'Authentication, first request, API exploration',
            ]);

            $content->push([
                'title' => 'WebSocket Demo',
                'description' => 'Interactive WebSocket demonstration',
                'url' => '/admin-panel/websocket-demo',
                'category' => 'development',
                'content' => 'Real-time events, WebSocket connection, live demo',
            ]);

            // API endpoints
            $content->push([
                'title' => 'Users API',
                'description' => 'User management endpoints',
                'url' => '/admin-panel/docs/api/users',
                'category' => 'api',
                'content' => 'GET /users, POST /users, PUT /users/{id}, DELETE /users/{id}',
            ]);

            $content->push([
                'title' => 'Teams API',
                'description' => 'Team management endpoints',
                'url' => '/admin-panel/docs/api/teams',
                'category' => 'api',
                'content' => 'GET /teams, POST /teams, team members, permissions',
            ]);

            $content->push([
                'title' => 'Orders API',
                'description' => 'Order management endpoints',
                'url' => '/admin-panel/docs/api/orders',
                'category' => 'api',
                'content' => 'GET /orders, POST /orders, order status, order items',
            ]);

            // Development guides
            $content->push([
                'title' => 'Backend Development',
                'description' => 'Laravel backend development guide',
                'url' => '/admin-panel/docs/development/backend',
                'category' => 'development',
                'content' => 'Laravel, PHP, database, migrations, models, controllers',
            ]);

            $content->push([
                'title' => 'Frontend Development',
                'description' => 'Frontend development with Livewire and Alpine.js',
                'url' => '/admin-panel/docs/development/frontend',
                'category' => 'development',
                'content' => 'Livewire, Alpine.js, Tailwind CSS, components',
            ]);

            $content->push([
                'title' => 'Testing Guide',
                'description' => 'Testing best practices and TDD approach',
                'url' => '/admin-panel/docs/development/testing',
                'category' => 'development',
                'content' => 'PHPUnit, TDD, feature tests, unit tests, test coverage',
            ]);

            // Integration guides
            $content->push([
                'title' => 'AWS Cognito Integration',
                'description' => 'Authentication with AWS Cognito',
                'url' => '/admin-panel/docs/integrations/cognito',
                'category' => 'integrations',
                'content' => 'JWT, authentication, Cognito, AWS',
            ]);

            $content->push([
                'title' => 'OneSignal Integration',
                'description' => 'Push notifications with OneSignal',
                'url' => '/admin-panel/docs/integrations/onesignal',
                'category' => 'integrations',
                'content' => 'Push notifications, OneSignal, mobile, web push',
            ]);

            // Reference
            $content->push([
                'title' => 'Environment Variables',
                'description' => 'Configuration and environment variables reference',
                'url' => '/admin-panel/docs/reference/env',
                'category' => 'reference',
                'content' => '.env, configuration, environment, settings',
            ]);

            $content->push([
                'title' => 'Database Schema',
                'description' => 'Database tables and relationships',
                'url' => '/admin-panel/docs/reference/database',
                'category' => 'reference',
                'content' => 'PostgreSQL, tables, migrations, relationships',
            ]);

            // Add dynamic content from markdown files if they exist
            $docsPath = resource_path('docs');
            if (File::isDirectory($docsPath)) {
                $files = File::allFiles($docsPath);
                foreach ($files as $file) {
                    if ($file->getExtension() === 'md') {
                        $content->push($this->parseMarkdownFile($file));
                    }
                }
            }

            return $content;
        });
    }

    private function parseMarkdownFile($file): array
    {
        $content = File::get($file->getPathname());
        $lines = explode("\n", $content);

        // Extract title from first heading
        $title = 'Untitled';
        foreach ($lines as $line) {
            if (Str::startsWith($line, '# ')) {
                $title = trim(Str::after($line, '# '));
                break;
            }
        }

        // Extract description from first paragraph
        $description = '';
        foreach ($lines as $line) {
            if (! Str::startsWith($line, '#') && ! empty(trim($line))) {
                $description = Str::limit(trim($line), 100);
                break;
            }
        }

        $filename = $file->getFilenameWithoutExtension();
        $category = 'documentation';

        // Determine category based on path
        if (Str::contains($file->getPath(), 'api')) {
            $category = 'api';
        } elseif (Str::contains($file->getPath(), 'development')) {
            $category = 'development';
        } elseif (Str::contains($file->getPath(), 'integrations')) {
            $category = 'integrations';
        }

        return [
            'title' => $title,
            'description' => $description,
            'url' => '/admin-panel/docs/'.Str::slug($filename),
            'category' => $category,
            'content' => Str::limit($content, 500),
        ];
    }

    private function calculateRelevance(string $text, string $query): float
    {
        $text = Str::lower($text);
        $query = Str::lower($query);

        // Exact match
        if ($text === $query) {
            return 10.0;
        }

        // Starts with query
        if (Str::startsWith($text, $query)) {
            return 5.0;
        }

        // Contains query
        if (Str::contains($text, $query)) {
            return 2.0;
        }

        // Contains all words from query
        $words = explode(' ', $query);
        $matchCount = 0;
        foreach ($words as $word) {
            if (Str::contains($text, $word)) {
                $matchCount++;
            }
        }

        return $matchCount / count($words);
    }

    private function highlightSearchTerms(string $text, string $query): string
    {
        $words = explode(' ', $query);

        foreach ($words as $word) {
            if (strlen($word) > 2) {
                $text = preg_replace(
                    '/('.preg_quote($word, '/').')/i',
                    '<mark>$1</mark>',
                    $text
                );
            }
        }

        return $text;
    }

    private function loadRecentSearches(): void
    {
        $userId = auth()->id();
        $this->recentSearches = Cache::get("recent_searches_{$userId}", []);
    }

    private function saveRecentSearch(string $query): void
    {
        if (empty($query)) {
            return;
        }

        $userId = auth()->id();
        $recent = Cache::get("recent_searches_{$userId}", []);

        // Remove if already exists
        $recent = array_diff($recent, [$query]);

        // Add to beginning
        array_unshift($recent, $query);

        // Keep only last 10
        $recent = array_slice($recent, 0, 10);

        Cache::put("recent_searches_{$userId}", $recent, 86400); // 24 hours
        $this->recentSearches = $recent;
    }
}
