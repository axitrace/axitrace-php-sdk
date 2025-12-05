<?php

declare(strict_types=1);

namespace AxiTrace;

use AxiTrace\Exception\ConfigurationException;

/**
 * Configuration class for AxiTrace SDK.
 */
class Config
{
    /**
     * Default API base URL.
     */
    public const DEFAULT_BASE_URL = 'https://stat.axitrace.com';

    /**
     * Default request timeout in seconds.
     */
    public const DEFAULT_TIMEOUT = 30;

    /**
     * SDK version.
     */
    public const SDK_VERSION = '1.0.0';

    /**
     * @var string
     */
    private string $secretKey;

    /**
     * @var string
     */
    private string $baseUrl;

    /**
     * @var int
     */
    private int $timeout;

    /**
     * @var bool
     */
    private bool $verifySsl;

    /**
     * @var bool
     */
    private bool $debug;

    /**
     * @param string $secretKey
     * @param array<string, mixed> $options
     * @throws ConfigurationException
     */
    public function __construct(string $secretKey, array $options = [])
    {
        $this->setSecretKey($secretKey);
        $this->baseUrl = $options['base_url'] ?? self::DEFAULT_BASE_URL;
        $this->timeout = $options['timeout'] ?? self::DEFAULT_TIMEOUT;
        $this->verifySsl = $options['verify_ssl'] ?? true;
        $this->debug = $options['debug'] ?? false;

        $this->validate();
    }

    /**
     * Create configuration from environment variables.
     *
     * @param array<string, mixed> $options
     * @return self
     * @throws ConfigurationException
     */
    public static function fromEnvironment(array $options = []): self
    {
        $secretKey = getenv('AXITRACE_SECRET_KEY');
        if ($secretKey === false || $secretKey === '') {
            throw ConfigurationException::missingSecretKey();
        }

        $envOptions = [];

        $baseUrl = getenv('AXITRACE_BASE_URL');
        if ($baseUrl !== false && $baseUrl !== '') {
            $envOptions['base_url'] = $baseUrl;
        }

        $timeout = getenv('AXITRACE_TIMEOUT');
        if ($timeout !== false && $timeout !== '') {
            $envOptions['timeout'] = (int) $timeout;
        }

        $verifySsl = getenv('AXITRACE_VERIFY_SSL');
        if ($verifySsl !== false && $verifySsl !== '') {
            $envOptions['verify_ssl'] = filter_var($verifySsl, FILTER_VALIDATE_BOOLEAN);
        }

        $debug = getenv('AXITRACE_DEBUG');
        if ($debug !== false && $debug !== '') {
            $envOptions['debug'] = filter_var($debug, FILTER_VALIDATE_BOOLEAN);
        }

        return new self($secretKey, array_merge($envOptions, $options));
    }

    /**
     * Validate configuration.
     *
     * @throws ConfigurationException
     */
    private function validate(): void
    {
        if (!$this->isValidUrl($this->baseUrl)) {
            throw ConfigurationException::invalidBaseUrl($this->baseUrl);
        }

        if ($this->timeout <= 0) {
            throw ConfigurationException::invalidTimeout($this->timeout);
        }
    }

    /**
     * Set and validate the secret key.
     *
     * @param string $secretKey
     * @throws ConfigurationException
     */
    private function setSecretKey(string $secretKey): void
    {
        $secretKey = trim($secretKey);

        if ($secretKey === '') {
            throw ConfigurationException::missingSecretKey();
        }

        // Allow sk_live_ and sk_test_ prefixes
        if (!preg_match('/^sk_(live|test)_/', $secretKey)) {
            throw ConfigurationException::invalidSecretKey($secretKey);
        }

        $this->secretKey = $secretKey;
    }

    /**
     * Check if URL is valid.
     *
     * @param string $url
     * @return bool
     */
    private function isValidUrl(string $url): bool
    {
        $parsed = parse_url($url);
        return isset($parsed['scheme'], $parsed['host'])
            && in_array($parsed['scheme'], ['http', 'https'], true);
    }

    /**
     * Get the secret key.
     *
     * @return string
     */
    public function getSecretKey(): string
    {
        return $this->secretKey;
    }

    /**
     * Get the base URL.
     *
     * @return string
     */
    public function getBaseUrl(): string
    {
        return rtrim($this->baseUrl, '/');
    }

    /**
     * Get the request timeout.
     *
     * @return int
     */
    public function getTimeout(): int
    {
        return $this->timeout;
    }

    /**
     * Check if SSL verification is enabled.
     *
     * @return bool
     */
    public function shouldVerifySsl(): bool
    {
        return $this->verifySsl;
    }

    /**
     * Check if debug mode is enabled.
     *
     * @return bool
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Check if using test mode (test key).
     *
     * @return bool
     */
    public function isTestMode(): bool
    {
        return strpos($this->secretKey, 'sk_test_') === 0;
    }

    /**
     * Get User-Agent string for API requests.
     *
     * @return string
     */
    public function getUserAgent(): string
    {
        return sprintf(
            'AxiTrace-PHP-SDK/%s PHP/%s',
            self::SDK_VERSION,
            PHP_VERSION
        );
    }

    /**
     * Set the base URL.
     *
     * @param string $baseUrl
     * @return self
     * @throws ConfigurationException
     */
    public function setBaseUrl(string $baseUrl): self
    {
        if (!$this->isValidUrl($baseUrl)) {
            throw ConfigurationException::invalidBaseUrl($baseUrl);
        }
        $this->baseUrl = $baseUrl;
        return $this;
    }

    /**
     * Set the request timeout.
     *
     * @param int $timeout
     * @return self
     * @throws ConfigurationException
     */
    public function setTimeout(int $timeout): self
    {
        if ($timeout <= 0) {
            throw ConfigurationException::invalidTimeout($timeout);
        }
        $this->timeout = $timeout;
        return $this;
    }

    /**
     * Set SSL verification.
     *
     * @param bool $verify
     * @return self
     */
    public function setVerifySsl(bool $verify): self
    {
        $this->verifySsl = $verify;
        return $this;
    }

    /**
     * Set debug mode.
     *
     * @param bool $debug
     * @return self
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }
}
