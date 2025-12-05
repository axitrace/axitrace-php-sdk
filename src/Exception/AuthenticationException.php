<?php

declare(strict_types=1);

namespace AxiTrace\Exception;

/**
 * Exception thrown when API authentication fails.
 */
class AuthenticationException extends AxiTraceException
{
    /**
     * Create exception for unauthorized access (401).
     *
     * @return self
     */
    public static function unauthorized(): self
    {
        return new self(
            'Authentication failed. Please check your API secret key.',
            401
        );
    }

    /**
     * Create exception for forbidden access (403).
     *
     * @param string|null $reason
     * @return self
     */
    public static function forbidden(?string $reason = null): self
    {
        $message = 'Access forbidden.';
        if ($reason !== null) {
            $message .= ' ' . $reason;
        }

        return new self($message, 403);
    }

    /**
     * Create exception for invalid workspace.
     *
     * @return self
     */
    public static function invalidWorkspace(): self
    {
        return new self(
            'Invalid workspace. The API key does not have access to this workspace.',
            403
        );
    }
}
