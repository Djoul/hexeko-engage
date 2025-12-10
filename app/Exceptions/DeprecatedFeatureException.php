<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DeprecatedFeatureException extends Exception
{
    public function __construct(
        private readonly string $deprecatedFeature,
        private readonly string $replacement,
        private readonly string $deprecatedDate = '2025-06-05'
    ) {
        $message = sprintf(
            'DEPRECATED: %s is no longer supported (deprecated since %s). Use %s instead.',
            $deprecatedFeature,
            $deprecatedDate,
            $replacement
        );

        parent::__construct($message);
    }

    /**
     * Report the exception.
     */
    public function report(): void
    {
        Log::warning('Deprecated feature used', [
            'deprecated_feature' => $this->deprecatedFeature,
            'replacement' => $this->replacement,
            'deprecated_date' => $this->deprecatedDate,
            'message' => $this->getMessage(),
            'trace' => $this->getTraceAsString(),
            'request_url' => request()->fullUrl(),
            'request_method' => request()->method(),
            'request_data' => request()->all(),
            'user_id' => auth()->id(),
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    /**
     * Render the exception into an HTTP response.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'error' => 'deprecated_feature',
            'message' => $this->getMessage(),
            'deprecated_feature' => $this->deprecatedFeature,
            'replacement' => $this->replacement,
            'deprecated_since' => $this->deprecatedDate,
        ], 410); // 410 Gone - indicates that the resource is no longer available
    }
}
