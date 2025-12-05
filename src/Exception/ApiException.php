<?php

declare(strict_types=1);

namespace AxiTrace\Exception;

/**
 * Exception thrown when API returns an error response.
 */
class ApiException extends AxiTraceException
{
    /**
     * @var int
     */
    protected int $statusCode;

    /**
     * @var string|null
     */
    protected ?string $errorBody;

    /**
     * @param string $message
     * @param int $statusCode
     * @param string|null $errorBody
     */
    public function __construct(string $message, int $statusCode, ?string $errorBody = null)
    {
        parent::__construct($message, $statusCode);
        $this->statusCode = $statusCode;
        $this->errorBody = $errorBody;
    }

    /**
     * Create exception from HTTP response.
     *
     * @param int $statusCode
     * @param string|null $responseBody
     * @return self
     */
    public static function fromResponse(int $statusCode, ?string $responseBody = null): self
    {
        $message = self::getMessageForStatusCode($statusCode);

        // Try to extract error message from response body
        if ($responseBody !== null) {
            $decoded = json_decode($responseBody, true);
            if (isset($decoded['error'])) {
                $message = $decoded['error'];
            } elseif (isset($decoded['message'])) {
                $message = $decoded['message'];
            }
        }

        return new self($message, $statusCode, $responseBody);
    }

    /**
     * Create exception for connection error.
     *
     * @param string $message
     * @return self
     */
    public static function connectionError(string $message): self
    {
        return new self(
            'Connection error: ' . $message,
            0,
            null
        );
    }

    /**
     * Create exception for timeout.
     *
     * @return self
     */
    public static function timeout(): self
    {
        return new self(
            'Request timeout. The server did not respond in time.',
            408,
            null
        );
    }

    /**
     * Create exception for rate limiting.
     *
     * @param int|null $retryAfter
     * @return self
     */
    public static function rateLimited(?int $retryAfter = null): self
    {
        $message = 'Rate limit exceeded.';
        if ($retryAfter !== null) {
            $message .= sprintf(' Retry after %d seconds.', $retryAfter);
        }

        return new self($message, 429, null);
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the raw error response body.
     *
     * @return string|null
     */
    public function getErrorBody(): ?string
    {
        return $this->errorBody;
    }

    /**
     * Get default message for HTTP status code.
     *
     * @param int $statusCode
     * @return string
     */
    private static function getMessageForStatusCode(int $statusCode): string
    {
        $messages = [
            400 => 'Bad request. Please check your request parameters.',
            401 => 'Unauthorized. Please check your API key.',
            403 => 'Forbidden. You do not have access to this resource.',
            404 => 'Not found. The requested resource does not exist.',
            405 => 'Method not allowed.',
            408 => 'Request timeout.',
            422 => 'Unprocessable entity. The request was well-formed but contains semantic errors.',
            429 => 'Too many requests. Please slow down.',
            500 => 'Internal server error. Please try again later.',
            502 => 'Bad gateway. The server received an invalid response.',
            503 => 'Service unavailable. Please try again later.',
            504 => 'Gateway timeout.',
        ];

        return $messages[$statusCode] ?? sprintf('HTTP error %d', $statusCode);
    }
}
