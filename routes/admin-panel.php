<?php

use App\Http\Controllers\AdminPanel\AdminPanelAuthController;
use App\Http\Controllers\AdminPanel\ApiDocsController;
use App\Http\Controllers\AdminPanel\DocsController;
use App\Http\Controllers\AdminPanel\FallbackController;
use App\Http\Controllers\AdminPanel\Manager\TranslationDashboardController;
use App\Http\Controllers\AdminPanel\SettingsController;
use App\Http\Controllers\AdminPanel\TranslationHealthController;
use App\Http\Controllers\AdminPanel\TranslationMigrationWebController;
use App\Http\Controllers\AdminPanel\TranslationWebController;
use App\Livewire\AdminPanel\ApiEndpointTester;
use App\Livewire\AdminPanel\DashboardPage;
use App\Livewire\AdminPanel\HomePage;
use App\Livewire\AdminPanel\InstallationPage;
use App\Livewire\AdminPanel\QuickStartPage;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Panel Routes
|--------------------------------------------------------------------------
|
| Here is where you can register admin panel routes for your application.
| These routes are loaded by the AdminPanelServiceProvider.
|
*/

// API routes for admin panel authentication
Route::prefix('api/v1/admin')->name('api.admin.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('/login', [AdminPanelAuthController::class, 'login'])->name('login');
        Route::post('/verify-mfa', [AdminPanelAuthController::class, 'verifyMfa'])->name('verify-mfa');
        Route::post('/refresh', [AdminPanelAuthController::class, 'refresh'])->name('refresh');
        Route::post('/logout', [AdminPanelAuthController::class, 'logout'])->name('logout');
        Route::get('/validate', [AdminPanelAuthController::class, 'validateToken'])->name('validate');
    });
});

Route::prefix('admin-panel')->name('admin.')->group(function (): void {
    // Public admin panel routes

    // Authentication routes
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::get('/login', [AdminPanelAuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AdminPanelAuthController::class, 'login'])->name('login.post');
        Route::get('/mfa', function (): Factory|View {
            return view('admin-panel.auth.mfa');
        })->name('mfa');

    });
});

Route::middleware(['web', 'livewire.token', 'admin.cognito'])
    ->prefix('admin-panel')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/test-auth', [AdminPanelAuthController::class, 'testAuth'])->name('test-auth');

        // Three Pillar Navigation
        Route::get('/', function () {
            return redirect()->route('admin.dashboard.index');
        })->name('index');

        // Dashboard Pillar
        Route::prefix('dashboard')->name('dashboard.')->group(function (): void {
            Route::get('/', DashboardPage::class)->name('index');

            // Dashboard placeholder routes
            Route::get('/overview', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('overview');

            Route::get('/metrics', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('metrics');

            Route::get('/health', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('health');

            Route::get('/queue', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('queue');

            Route::get('/services', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('services');

            Route::get('/analytics', [DocsController::class, 'index'])->name('analytics');
            Route::get('/alerts', [DocsController::class, 'index'])->name('alerts');
        });

        // Manager Pillar
        Route::prefix('manager')->name('manager.')->group(function (): void {
            Route::get('/', [DocsController::class, 'index'])->name('index');

            // Translation Management
            Route::prefix('translations')->name('translations.')->group(function (): void {
                Route::get('/', [TranslationDashboardController::class, 'index'])->name('index');
                Route::post('/refresh', [TranslationDashboardController::class, 'refresh'])->name('refresh');
                Route::get('/editor', [TranslationWebController::class, 'index'])->name('editor');
                Route::get('/migrations', [TranslationMigrationWebController::class, 'index'])->name('migrations');
                Route::get('/health', [TranslationHealthController::class, 'health'])->name('health');
            });

            // Placeholder routes - Under Construction
            Route::get('/migrations', function () {
                return app(FallbackController::class)->underConstruction('migrations');
            })->name('migrations');

            Route::get('/roles', function () {
                return app(FallbackController::class)->underConstruction('roles');
            })->name('roles');

            Route::get('/permissions', function () {
                return app(FallbackController::class)->underConstruction('roles');
            })->name('permissions');

            Route::get('/audit', function () {
                return app(FallbackController::class)->underConstruction('audit');
            })->name('audit');

            Route::get('/integrations', [DocsController::class, 'index'])->name('integrations');
            Route::put('/translations/settings/locales', [SettingsController::class, 'updateAvailableLocales'])->name('translations.settings.update-locales');
        });

        // Documentation Pillar
        Route::prefix('docs')->name('docs.')->group(function (): void {
            // Main documentation pages using Livewire components
            Route::get('/', HomePage::class)->name('index');
            Route::get('/home', HomePage::class)->name('home');
            Route::get('/installation', InstallationPage::class)->name('installation');
            Route::get('/quick-start', QuickStartPage::class)->name('quick-start');

            // Section routes
            Route::get('/getting-started', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('getting-started');

            Route::get('/api', function (Generator $generator): Factory|View {
                $config = Scramble::getGeneratorConfig('default');

                if (! app()->environment('local')) {
                    $specPath = base_path('api.json');

                    if (is_file($specPath)) {
                        try {
                            $spec = json_decode(file_get_contents($specPath), true, 512, JSON_THROW_ON_ERROR);

                            return view('scramble::docs', [
                                'spec' => $spec,
                                'config' => $config,
                            ]);
                        } catch (JsonException) {
                            // Fallback to dynamic generation if cached spec is invalid JSON.
                        }
                    }
                }

                return view('scramble::docs', [
                    'spec' => $generator($config),
                    'config' => $config,
                ]);
            })->name('api');

            Route::get('/development', function () {
                return app(FallbackController::class)->underConstruction('development');
            })->name('development');

            Route::get('/integrations', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('integrations');

            Route::get('/reference', function () {
                return app(FallbackController::class)->underConstruction('default');
            })->name('reference');

            // Legacy routes kept for backward compatibility
            Route::get('/technical', [DocsController::class, 'index'])->name('technical');
            Route::get('/business', [DocsController::class, 'index'])->name('business');
            Route::get('/faqs', [DocsController::class, 'index'])->name('faqs');
        });

        // Legacy route redirect
        Route::get('/quickstart', [DocsController::class, 'quickstart'])->name('quickstart');
        Route::post('/logout', [AdminPanelAuthController::class, 'logout'])->name('logout');
        // Admin panel pages
        Route::get('/under-construction', [DocsController::class, 'underConstruction'])->name('under-construction');
        Route::get('/make-commands', [DocsController::class, 'makeCommands'])->name('make-commands');
        Route::get('/development', [DocsController::class, 'development'])->name('development');
        Route::get('/testing', [DocsController::class, 'testing'])->name('testing');

        // Livewire components routes
        Route::get('/api-tester', ApiEndpointTester::class)->name('api-tester');

        // Translation management
        Route::get('/translations', [TranslationWebController::class, 'index'])->name('translations.index');

        // Translation migrations - Using Livewire component
        Route::get('/translation-migrations', [TranslationMigrationWebController::class, 'index'])->name('translation-migrations.index');

        // Legacy routes - kept for backward compatibility but handled by Livewire component
        Route::prefix('translation-migrations')->group(function (): void {
            Route::get('/{translationMigration}', [TranslationMigrationWebController::class, 'show'])->name('translation-migrations.show');
            Route::post('/{translationMigration}/apply', [TranslationMigrationWebController::class, 'apply'])->name('translation-migrations.apply');
            Route::post('/{translationMigration}/rollback', [TranslationMigrationWebController::class, 'rollback'])->name('translation-migrations.rollback');
            Route::post('/sync', [TranslationMigrationWebController::class, 'sync'])->name('translation-migrations.sync');
        });

        // Translation Health & Protection
        Route::prefix('translations')->group(function (): void {
            Route::get('/health', [TranslationHealthController::class, 'health'])->name('translations.health');
            Route::get('/drift', [TranslationHealthController::class, 'detectDrift'])->name('translations.drift');
            Route::post('/reconcile', [TranslationHealthController::class, 'reconcile'])->name('translations.reconcile');
            Route::get('/manifest/{interface}', [TranslationHealthController::class, 'getManifest'])->name('translations.manifest');
            Route::delete('/cache', [TranslationHealthController::class, 'clearCache'])->name('translations.cache.clear');
        });

        // API admin panel
        Route::prefix('api')->name('api.')->group(function (): void {
            Route::get('/', [ApiDocsController::class, 'index'])->name('index');
            Route::get('/{endpoint}', [ApiDocsController::class, 'show'])
                ->where('endpoint', '.*')
                ->name('show');
        });

        // Interactive demos
        Route::get('/websocket-demo', [DocsController::class, 'websocketDemo'])->name('websocket-demo');
    });
