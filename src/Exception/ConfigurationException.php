<?php

declare(strict_types=1);

namespace AxiTrace\Exception;

/**
 * Exception thrown when SDK configuration is invalid or missing.
 */
class ConfigurationException extends AxiTraceException
{
    /**
     * Create exception for missing secret key.
     *
     * @return self
     */
    public static function missingSecretKey(): self
    {
        return new self(
            'Secret key is required. Please provide a valid AxiTrace secret key.',
            400
        );
    }

    /**
     * Create exception for invalid secret key format.
     *
     * @param string $key
     * @return self
     */
    public static function invalidSecretKey(string $key): self
    {
        return new self(
            'Invalid secret key format. Secret key should start with "sk_live_" or "sk_test_".',
            400,
            null,
            ['provided_prefix' => substr($key, 0, 8) . '...']
        );
    }

    /**
     * Create exception for invalid base URL.
     *
     * @param string $url
     * @return self
     */
    public static function invalidBaseUrl(string $url): self
    {
        return new self(
            'Invalid base URL provided. Please provide a valid HTTP/HTTPS URL.',
            400,
            null,
            ['provided_url' => $url]
        );
    }

    /**
     * Create exception for invalid timeout value.
     *
     * @param int $timeout
     * @return self
     */
    public static function invalidTimeout(int $timeout): self
    {
        return new self(
            'Invalid timeout value. Timeout must be a positive integer.',
            400,
            null,
            ['provided_timeout' => $timeout]
        );
    }
}
