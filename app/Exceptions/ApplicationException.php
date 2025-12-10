<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Exception for application-level errors.
 *
 * This exception should be used for business logic errors, validation failures,
 * and other application-specific exceptions that need to be communicated to the user.
 *
 * The actual rendering (JSON for HTTP/API, plain text for CLI) is automatically
 * handled by the global exception handler in bootstrap/app.php based on context.
 *
 * @example throw new ApplicationException('Survey cannot be published', ['reason' => 'no questions'], 422);
 */
class ApplicationException extends Exception
{
    protected ?array $context;

    protected int $httpStatusCode;

    public function __construct(
        string $message = 'API Error',
        ?array $context = null,
        int $httpStatusCode = 422,
        int $code = 0,
        ?Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
        $this->httpStatusCode = $httpStatusCode;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getErrorType(): string
    {
        return class_basename(static::class);
    }
}
