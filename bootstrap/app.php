<?php

use App\Http\Middleware\LogRequest;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

$testEnvFile = __DIR__.'/../.env.testing';

if (file_exists($testEnvFile) && (getenv('APP_ENV') === 'testing' || ($_SERVER['APP_ENV'] ?? '') === 'testing' || ($_ENV['APP_ENV'] ?? '') === 'testing')) {
    // Use createMutable to allow overwriting existing env vars
    $dotenv = Dotenv\Dotenv::createMutable(__DIR__.'/../', '.env.testing');
    $dotenv->load();

    // Force APP_ENV to testing AFTER loading .env.testing to ensure Laravel uses the correct environment
    putenv('APP_ENV=testing');
    $_ENV['APP_ENV'] = 'testing';
    $_SERVER['APP_ENV'] = 'testing';

    // Force the test database configuration
    $_SERVER['DB_HOST'] = 'db_engage_testing';
    $_SERVER['DB_DATABASE'] = 'db_engage_testing';
    $_ENV['DB_HOST'] = 'db_engage_testing';
    $_ENV['DB_DATABASE'] = 'db_engage_testing';
    putenv('DB_HOST=db_engage_testing');
    putenv('DB_DATABASE=db_engage_testing');
}

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: 'api/v1/health',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust proxies for correct HTTPS detection
        $middleware->trustProxies(at: '*');

        // Force HTTPS in production environments
        $middleware->append(\App\Http\Middleware\ForceHttps::class);

        $middleware->append(LogRequest::class);

        $middleware->alias([
            'auth.cognito' => \App\Http\Middleware\CognitoAuthMiddleware::class,
            'admin.cognito' => \App\Http\Middleware\AdminCognitoMiddleware::class,
            'admin.panel.auth' => \App\Http\Middleware\AdminPanelAuth::class,
            'livewire.token' => \App\Http\Middleware\LivewireTokenMiddleware::class,
            'check.permission' => \App\Http\Middleware\CheckPermissionAttribute::class,
            'check.credit' => \App\Http\Middleware\CheckCreditQuotaMiddleware::class,
            'check.active.financer' => \App\Http\Middleware\CheckActiveFinancerMiddleware::class,
            'check.allowed.financer' => \App\Http\Middleware\CheckAllowedFinancerMiddleware::class,
            'tenant.guard' => \App\Http\Middleware\TenantGuard::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'test-reverb-public-message',
            'test-reverb-stats',
            'test-reverb-apideck',
            'test-broadcasting/*',
            'api/*', // API routes are stateless and use Bearer auth
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle ApplicationException - Only render as JSON if in HTTP/JSON context
        $exceptions->render(function (\App\Exceptions\ApplicationException $e, \Illuminate\Http\Request $request) {
            // Check if we should return JSON (API request or expects JSON)
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'error' => $e->getErrorType(),
                    'message' => $e->getMessage(),
                    'context' => $e->getContext(),
                ], $e->getHttpStatusCode());
            }

            // For non-JSON contexts (CLI, jobs, commands), return null to use Laravel's default handling
            return null;
        });

        // Custom exception rendering for admin panel
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, \Illuminate\Http\Request $request) {
            if ($request->is('admin-panel/*') || $request->routeIs('admin.*')) {
                session(['intended' => $request->fullUrl()]);

                return redirect()->route('admin.auth.login')
                    ->with('error', 'Please login to access the admin panel.');
            }
        });

        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 403 && ($request->is('admin-panel/*') || $request->routeIs('admin.*'))) {
                session(['intended' => $request->fullUrl()]);

                return redirect()->route('admin.auth.login')
                    ->with('error', 'Please login to access the admin panel.');
            }
        });

        if (in_array(app()->environment(), ['local', 'testing'])) {
            return;
        }

        Integration::handles($exceptions);

    })->create();
