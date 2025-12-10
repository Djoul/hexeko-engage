<?php

namespace App\Providers;

use App\Http\Middleware\AdminPanelAuthMiddleware;
use App\Http\Middleware\AdminPanelEnabled;
use App\Livewire\AdminPanel\ApiEndpointTester;
use App\Livewire\AdminPanel\SearchBar;
use App\Livewire\AdminPanel\WebSocketDemo;
use App\Services\AdminPanel\AdminPanelParser;
use App\Services\AdminPanel\ApiSchemaExtractor;
use App\Services\AdminPanel\JwtSessionBridge;
use Dedoc\Scramble\Scramble;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AdminPanelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(JwtSessionBridge::class);
        $this->app->singleton(AdminPanelParser::class);
        $this->app->singleton(ApiSchemaExtractor::class);
    }

    public function boot(): void
    {
        // Keep existing Scramble configuration
        Scramble::registerApi('v1', [
            'api_path' => 'api/v1',
        ]);

        // Always register middleware
        $this->registerMiddleware();

        // Only boot documentation features if enabled
        if (! config('admin-panel.enabled', false)) {
            return;
        }

        // Load routes
        $this->loadRoutes();

        // Register Livewire components
        $this->registerLivewireComponents();

        // Register console commands
        $this->registerCommands();

        // Publish configuration
        $this->publishConfiguration();

        // Publish views
        $this->publishViews();
    }

    private function loadRoutes(): void
    {
        $routesPath = base_path('routes/admin-panel.php');

        if (file_exists($routesPath)) {
            Route::middleware('web')
                ->group($routesPath);
        }
    }

    private function registerMiddleware(): void
    {
        $this->app['router']->aliasMiddleware('admin.auth', AdminPanelAuthMiddleware::class);
        $this->app['router']->aliasMiddleware('admin.enabled', AdminPanelEnabled::class);
    }

    private function registerLivewireComponents(): void
    {
        if (class_exists(Livewire::class)) {
            Livewire::component('admin-panel.api-endpoint-tester', ApiEndpointTester::class);
            Livewire::component('admin-panel.search-bar', SearchBar::class);
            Livewire::component('admin-panel.websocket-demo', WebSocketDemo::class);
        }
    }

    private function registerCommands(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                // Commands will be added later
            ]);
        }
    }

    private function publishConfiguration(): void
    {
        $this->publishes([
            __DIR__.'/../../config/admin-panel.php' => config_path('admin-panel.php'),
        ], 'admin-panel-config');
    }

    private function publishViews(): void
    {
        $this->publishes([
            __DIR__.'/../../resources/views/admin-panel' => resource_path('views/admin-panel'),
        ], 'admin-panel-views');
    }
}
