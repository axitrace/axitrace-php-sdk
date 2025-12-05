<?php

declare(strict_types=1);

namespace AxiTrace\Helper;

/**
 * Validation helper for common validation tasks.
 */
class Validator
{
    /**
     * Common ISO 4217 currency codes.
     */
    private const COMMON_CURRENCIES = [
        'USD', 'EUR', 'GBP', 'JPY', 'AUD', 'CAD', 'CHF', 'CNY', 'HKD', 'NZD',
        'SEK', 'KRW', 'SGD', 'NOK', 'MXN', 'INR', 'RUB', 'ZAR', 'TRY', 'BRL',
        'TWD', 'DKK', 'PLN', 'THB', 'IDR', 'HUF', 'CZK', 'ILS', 'CLP', 'PHP',
        'AED', 'COP', 'SAR', 'MYR', 'RON', 'BGN', 'HRK', 'PEN', 'UAH', 'VND',
    ];

    /**
     * Check if email is valid.
     *
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Check if currency code is valid.
     *
     * @param string $currency
     * @return bool
     */
    public static function isValidCurrency(string $currency): bool
    {
        // Check if it's a 3-letter uppercase code
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($currency))) {
            return false;
        }

        // Accept any 3-letter code (ISO 4217 has many currencies)
        return true;
    }

    /**
     * Check if currency is a common one.
     *
     * @param string $currency
     * @return bool
     */
    public static function isCommonCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::COMMON_CURRENCIES, true);
    }

    /**
     * Check if URL is valid.
     *
     * @param string $url
     * @return bool
     */
    public static function isValidUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Check if value is positive.
     *
     * @param float|int $value
     * @return bool
     */
    public static function isPositive($value): bool
    {
        return is_numeric($value) && $value > 0;
    }

    /**
     * Check if value is non-negative.
     *
     * @param float|int $value
     * @return bool
     */
    public static function isNonNegative($value): bool
    {
        return is_numeric($value) && $value >= 0;
    }

    /**
     * Check if string is not empty.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isNotEmpty($value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value)) {
            return trim($value) !== '';
        }

        if (is_array($value)) {
            return !empty($value);
        }

        return true;
    }

    /**
     * Sanitize string for safe use.
     *
     * @param string $value
     * @return string
     */
    public static function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Validate phone number format (E.164).
     *
     * @param string $phone
     * @return bool
     */
    public static function isValidPhoneE164(string $phone): bool
    {
        // E.164 format: +[country code][number], max 15 digits
        return preg_match('/^\+[1-9]\d{1,14}$/', $phone) === 1;
    }

    /**
     * Check if value is a valid country code (ISO 3166-1 alpha-2).
     *
     * @param string $country
     * @return bool
     */
    public static function isValidCountryCode(string $country): bool
    {
        return preg_match('/^[A-Z]{2}$/', strtoupper($country)) === 1;
    }
}
