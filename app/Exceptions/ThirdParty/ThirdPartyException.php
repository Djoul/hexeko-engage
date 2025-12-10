<?php

declare(strict_types=1);

namespace App\Exceptions\ThirdParty;

use Exception;
use Illuminate\Http\JsonResponse;
use Throwable;

class ThirdPartyException extends Exception
{
    protected string $provider;

    /** @var array<string, mixed>|null */
    protected ?array $responseBody;

    protected int $httpStatus;

    /**
     * @param  array<string, mixed>|null  $responseBody
     */
    public function __construct(
        string $provider,
        string $message,
        int $httpStatus = 0,
        ?array $responseBody = null,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $httpStatus, $previous);

        $this->provider = $provider;
        $this->httpStatus = $httpStatus;
        $this->responseBody = $responseBody;
    }

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponseBody(): ?array
    {
        return $this->responseBody;
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'error' => 'Third Party API Error',
            'message' => $this->getUserFriendlyMessage(),
            'provider' => $this->provider,
        ], 422);
    }

    protected function getUserFriendlyMessage(): string
    {
        return match ($this->httpStatus) {
            401 => "Authentication with {$this->provider} service failed",
            403 => "Access denied by {$this->provider} service",
            404 => "Resource not found on {$this->provider} service",
            429 => "Rate limit exceeded for {$this->provider} service",
            500, 502, 503 => "{$this->provider} service is temporarily unavailable",
            default => "Error communicating with {$this->provider} service",
        };
    }
}
