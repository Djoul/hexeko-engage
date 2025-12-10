<?php

namespace App\Http\Controllers\AdminPanel;

use App\Http\Controllers\Controller;
use App\Services\AdminPanel\ApiSchemaExtractor;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

class ApiDocsController extends Controller
{
    public function __construct(
        private ApiSchemaExtractor $schemaExtractor
    ) {}

    public function index(): View
    {
        $endpoints = $this->schemaExtractor->groupEndpointsByController();

        return view('admin-panel.pages.api.index', [
            'endpoints' => $endpoints,
            'title' => 'API Reference',
            'breadcrumbs' => [
                ['url' => route('admin.index'), 'label' => 'Documentation'],
                ['label' => 'API Reference'],
            ],
        ]);
    }

    public function show(string $endpoint): View
    {
        // Find the route by name or path
        $route = Route::getRoutes()->getByName($endpoint)
            ?? $this->findRouteByPath($endpoint);

        if (! $route) {
            abort(404);
        }

        if (! $route instanceof \Illuminate\Routing\Route) {
            abort(404);
        }

        $details = $this->schemaExtractor->extractFullEndpointDetails($route);

        return view('admin-panel.pages.api.show', [
            'endpoint' => $details,
            'title' => $details['name'] ?? $endpoint,
            'breadcrumbs' => [
                ['url' => route('admin.index'), 'label' => 'Documentation'],
                ['url' => route('admin.api.index'), 'label' => 'API Reference'],
                ['label' => $details['name'] ?? $endpoint],
            ],
        ]);
    }

    private function findRouteByPath(string $path): ?\Illuminate\Routing\Route
    {
        $routes = Route::getRoutes();

        if (! method_exists($routes, 'getIterator')) {
            return null;
        }

        /** @var \Illuminate\Routing\Route $route */
        foreach ($routes->getIterator() as $route) {
            if ($route->uri() === $path) {
                return $route;
            }
        }

        return null;
    }
}
