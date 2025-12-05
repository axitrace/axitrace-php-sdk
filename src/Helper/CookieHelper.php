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
     * @return string|null
     */
    public static function getFbp(): ?string
    {
        return self::getCookie(self::COOKIE_FBP);
    }

    /**
     * Get Facebook click ID from cookie.
     *
     * @return string|null
     */
    public static function getFbc(): ?string
    {
        return self::getCookie(self::COOKIE_FBC);
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
