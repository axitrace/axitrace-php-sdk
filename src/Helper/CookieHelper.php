<?php

declare(strict_types=1);

namespace AxiTrace\Helper;

/**
 * Helper for reading AxiTrace and Facebook cookies.
 */
class CookieHelper
{
    /**
     * AxiTrace visitor ID cookie name.
     */
    public const COOKIE_VISITOR_ID = 'vt_vid';

    /**
     * AxiTrace session ID cookie name.
     */
    public const COOKIE_SESSION_ID = 'vt_sid';

    /**
     * AxiTrace user ID cookie name.
     */
    public const COOKIE_USER_ID = 'vt_uid';

    /**
     * Facebook pixel cookie name.
     */
    public const COOKIE_FBP = '_fbp';

    /**
     * Facebook click ID cookie name.
     */
    public const COOKIE_FBC = '_fbc';

    /**
     * Get visitor ID from cookie.
     *
     * @return string|null
     */
    public static function getVisitorId(): ?string
    {
        return self::getCookie(self::COOKIE_VISITOR_ID);
    }

    /**
     * Get session ID from cookie.
     *
     * @return string|null
     */
    public static function getSessionId(): ?string
    {
        return self::getCookie(self::COOKIE_SESSION_ID);
    }

    /**
     * Get user ID from cookie.
     *
     * @return string|null
     */
    public static function getUserId(): ?string
    {
        return self::getCookie(self::COOKIE_USER_ID);
    }

    /**
     * Get Facebook pixel ID from cookie.
     *
     * SECURITY: Validates the cookie format before returning.
     * Valid format: fb.X.TIMESTAMP.VALUE (e.g., fb.1.1558571054389.1098115397)
     *
     * @return string|null Validated _fbp value or null if invalid/missing
     */
    public static function getFbp(): ?string
    {
        $value = self::getCookie(self::COOKIE_FBP);
        if ($value === null) {
            return null;
        }

        // Validate Facebook cookie format
        if (!self::isValidFacebookCookie($value)) {
            return null;
        }

        return self::sanitizeCookieValue($value);
    }

    /**
     * Get Facebook click ID from cookie.
     *
     * SECURITY: Validates the cookie format before returning.
     * Valid format: fb.X.TIMESTAMP.VALUE (e.g., fb.1.1554763741205.AbCdEfGhIjKl)
     *
     * @return string|null Validated _fbc value or null if invalid/missing
     */
    public static function getFbc(): ?string
    {
        $value = self::getCookie(self::COOKIE_FBC);
        if ($value === null) {
            return null;
        }

        // Validate Facebook cookie format
        if (!self::isValidFacebookCookie($value)) {
            return null;
        }

        return self::sanitizeCookieValue($value);
    }

    /**
     * Validate Facebook cookie format (_fbp or _fbc).
     *
     * Format: fb.X.TIMESTAMP.VALUE
     * - _fbp: fb.1.1558571054389.1098115397
     * - _fbc: fb.1.1554763741205.AbCdEfGhIjKl...
     *
     * @param string $value Cookie value
     * @return bool True if valid format
     */
    private static function isValidFacebookCookie(string $value): bool
    {
        return (bool) preg_match('/^fb\.\d+\.\d+\..+$/', $value);
    }

    /**
     * Sanitize cookie value to prevent injection attacks.
     *
     * @param string $value Raw cookie value
     * @return string Sanitized value
     */
    private static function sanitizeCookieValue(string $value): string
    {
        // Limit length to prevent memory exhaustion (500 chars is plenty for any cookie)
        $value = substr($value, 0, 500);

        // Remove control characters (0x00-0x1F and 0x7F) but keep printable chars
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return trim($value ?? '');
    }

    /**
     * Get all AxiTrace cookies.
     *
     * @return array<string, string|null>
     */
    public static function getAllAxiTraceCookies(): array
    {
        return [
            'visitor_id' => self::getVisitorId(),
            'session_id' => self::getSessionId(),
            'user_id' => self::getUserId(),
        ];
    }

    /**
     * Get all Facebook cookies.
     *
     * @return array<string, string|null>
     */
    public static function getAllFacebookCookies(): array
    {
        return [
            'fbp' => self::getFbp(),
            'fbc' => self::getFbc(),
        ];
    }

    /**
     * Get a cookie value.
     *
     * @param string $name
     * @return string|null
     */
    private static function getCookie(string $name): ?string
    {
        if (!isset($_COOKIE[$name])) {
            return null;
        }

        $value = $_COOKIE[$name];
        if ($value === '' || $value === '0') {
            return null;
        }

        return $value;
    }
}
