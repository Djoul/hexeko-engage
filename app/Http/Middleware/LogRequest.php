<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class LogRequest
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse
    {
        // Skip logging for excluded paths
        if ($this->shouldSkipLogging($request)) {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            return $next($request);
        }

        // Skip logging if we can't write to storage (permissions issue)
        if (! $this->canWriteLogs()) {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            return $next($request);
        }

        $startTime = microtime(true);
        $requestId = $this->generateRequestId();

        // Add request ID to log context
        Log::withContext([
            'request_id' => $requestId,
        ]);

        // Log request start with structured format
        // Note: User info is not available yet as auth middleware hasn't run
        $logTitle = sprintf('[%s] %s', $request->method(), $request->path());

        Log::info($logTitle, [
            'event' => 'request.start',
            'request_id' => $requestId,
            'http' => [
                'method' => $request->method(),
                'path' => $request->path(),
                'host' => $request->getHost(),
            ],
            'client' => [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ],
            'params' => [
                'query' => $this->sanitizeData($request->query->all()),
                'body' => $this->resolveRequestLoggingBool('log_body', false)
                    ? $this->sanitizeData($request->except(['password', 'password_confirmation', 'token']))
                    : null,
            ],
            'timestamp' => now()->toIso8601String(),
        ]);

        try {
            /** @var Response|JsonResponse|RedirectResponse|BinaryFileResponse|StreamedResponse */
            $response = $next($request);
            assert(
                $response instanceof Response ||
                $response instanceof JsonResponse ||
                $response instanceof RedirectResponse ||
                $response instanceof BinaryFileResponse ||
                $response instanceof StreamedResponse
            );

            // Add X-Request-ID header to response
            $response->headers->set('X-Request-ID', $requestId);

            $statusCode = $response->getStatusCode();
            $logLevel = $this->getLogLevel($statusCode);
        } catch (Throwable $exception) {
            // Log request failure before re-throwing
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);
            $routeName = $request->route()?->getName();
            $errorLogTitle = $routeName
                ? sprintf('[%s] %s (EXCEPTION)', $request->method(), $routeName)
                : sprintf('[%s] %s (EXCEPTION)', $request->method(), $request->path());

            Log::error($errorLogTitle, [
                'event' => 'request.exception',
                'request_id' => $requestId,
                'http' => [
                    'route_name' => $routeName,
                ],
                'user' => [
                    'id' => auth()->id(),
                    'email' => auth()->user()?->email,
                ],
                'exception' => [
                    'class' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                ],
                'performance' => [
                    'execution_time_ms' => $executionTime,
                    'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                ],
                'timestamp' => now()->toIso8601String(),
            ]);

            throw $exception;
        }

        // Calculate execution time
        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        // Build descriptive log title with route name if available
        $routeName = $request->route()?->getName();
        $endLogTitle = $routeName
            ? sprintf('[%s] %s (%s)', $request->method(), $routeName, $statusCode)
            : sprintf('[%s] %s (%s)', $request->method(), $request->path(), $statusCode);

        // Log request end with structured format
        // User info is available here because auth middleware has already run
        Log::$logLevel($endLogTitle, [
            'event' => 'request.end',
            'request_id' => $requestId,
            'http' => [
                'status_code' => $statusCode,
                'route_name' => $routeName,
            ],
            'user' => [
                'id' => auth()->id(),
                'email' => auth()->user()?->email,
            ],
            'performance' => [
                'execution_time_ms' => $executionTime,
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ],
            'timestamp' => now()->toIso8601String(),
        ]);

        return $response;
    }

    /**
     * Sanitize sensitive data from request parameters.
     *
     * @param  array<mixed>  $data
     * @return array<mixed>
     */
    private function sanitizeData(array $data): array
    {
        $sensitive = [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'api_key',
            'api_secret',
            'access_token',
            'refresh_token',
            'credit_card',
            'card_number',
            'cvv',
            'ssn',
        ];

        $result = [];
        foreach ($data as $key => $value) {
            // Check if key contains sensitive words
            $keyString = (string) $key;
            $isSensitive = false;

            foreach ($sensitive as $sensitiveWord) {
                if (Str::contains(strtolower($keyString), $sensitiveWord)) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $result[$keyString] = '***REDACTED***';
            } elseif (is_array($value)) {
                $result[$keyString] = $this->sanitizeData($value);
            } else {
                $result[$keyString] = $value;
            }
        }

        return $result;
    }

    /**
     * Determine if logging should be skipped for this request.
     */
    private function shouldSkipLogging(Request $request): bool
    {
        if (! $this->resolveRequestLoggingBool('enabled', true)) {
            return true;
        }

        // Get current path (without leading slash for consistent matching)
        $currentPath = ltrim($request->path(), '/');
        // Check excluded paths
        /** @var array<int, string> $excludedPaths */
        $excludedPaths = $this->resolveRequestLoggingArray('excluded_paths', []);

        foreach ($excludedPaths as $path) {
            // Normalize path pattern (remove leading slash if present)
            $normalizedPattern = ltrim($path, '/');

            // Use Str::is for wildcard matching
            if (Str::is($normalizedPattern, $currentPath)) {
                return true;
            }
        }

        // Check excluded route names
        /** @var array<int, string> $excludedRouteNames */
        $excludedRouteNames = $this->resolveRequestLoggingArray('excluded_route_names', []);
        $routeName = $request->route()?->getName();

        return $routeName && in_array($routeName, $excludedRouteNames, true);
    }

    /**
     * Get log level based on HTTP status code.
     */
    private function getLogLevel(int $statusCode): string
    {
        return match (true) {
            $statusCode >= 500 => 'error',
            $statusCode >= 400 => 'warning',
            default => 'info',
        };
    }

    /**
     * Check if we can write logs to storage.
     * Prevents fatal errors when storage permissions are misconfigured.
     */
    private function canWriteLogs(): bool
    {
        $logPath = storage_path('logs');

        // Check if logs directory exists and is writable
        if (! is_dir($logPath)) {
            return false;
        }

        return is_writable($logPath);
    }

    /**
     * Resolve request logging configuration value, supporting both legacy and channel-scoped keys.
     */
    private function resolveRequestLoggingConfig(string $key, mixed $default = null): mixed
    {
        $value = config("logging.request_logging.$key");

        if ($value !== null) {
            return $value;
        }

        $channelValue = config("logging.channels.request_logging.$key");

        if ($channelValue !== null) {
            return $channelValue;
        }

        return $default;
    }

    private function resolveRequestLoggingBool(string $key, bool $default): bool
    {
        $value = $this->resolveRequestLoggingConfig($key, $default);

        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            $normalized = strtolower($value);

            if (in_array($normalized, ['1', 'true', 'on', 'yes'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'off', 'no'], true)) {
                return false;
            }
        }

        return (bool) $value;
    }

    /**
     * @param  array<mixed>  $default
     * @return array<mixed>
     */
    private function resolveRequestLoggingArray(string $key, array $default): array
    {
        $value = $this->resolveRequestLoggingConfig($key, $default);

        return is_array($value) ? $value : $default;
    }

    private function generateRequestId(): string
    {
        $uuid = (string) Str::uuid();

        return str_replace('-', '', $uuid);
    }
}
