<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class InvalidLanguageException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => in_array($this->getMessage(), ['', '0'], true) ? 'Language not available for this financer' : $this->getMessage(),
            'error' => 'invalid_language',
        ], 422);
    }
}
