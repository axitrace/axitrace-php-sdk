<?php

declare(strict_types=1);

namespace AxiTrace\Exception;

/**
 * Exception thrown when event data validation fails.
 */
class ValidationException extends AxiTraceException
{
    /**
     * @var array<string, string>
     */
    protected array $errors = [];

    /**
     * Create exception for missing required field.
     *
     * @param string $field
     * @param string|null $eventType
     * @return self
     */
    public static function missingRequiredField(string $field, ?string $eventType = null): self
    {
        $message = sprintf('Missing required field: %s', $field);
        if ($eventType !== null) {
            $message = sprintf('Missing required field "%s" for %s event', $field, $eventType);
        }

        return new self($message, 400, null, ['field' => $field, 'event_type' => $eventType]);
    }

    /**
     * Create exception for missing user identifier.
     *
     * @return self
     */
    public static function missingUserIdentifier(): self
    {
        return new self(
            'At least one of client_id, user_id, or session_id is required.',
            400
        );
    }

    /**
     * Create exception for invalid field type.
     *
     * @param string $field
     * @param string $expectedType
     * @param string $actualType
     * @return self
     */
    public static function invalidFieldType(string $field, string $expectedType, string $actualType): self
    {
        return new self(
            sprintf('Invalid type for field "%s". Expected %s, got %s.', $field, $expectedType, $actualType),
            400,
            null,
            ['field' => $field, 'expected_type' => $expectedType, 'actual_type' => $actualType]
        );
    }

    /**
     * Create exception for invalid email format.
     *
     * @param string $email
     * @return self
     */
    public static function invalidEmail(string $email): self
    {
        return new self(
            'Invalid email format provided.',
            400,
            null,
            ['email' => $email]
        );
    }

    /**
     * Create exception for invalid currency code.
     *
     * @param string $currency
     * @return self
     */
    public static function invalidCurrency(string $currency): self
    {
        return new self(
            sprintf('Invalid currency code "%s". Please use ISO 4217 currency codes (e.g., USD, EUR).', $currency),
            400,
            null,
            ['currency' => $currency]
        );
    }

    /**
     * Create exception for invalid value (must be positive).
     *
     * @param string $field
     * @param float $value
     * @return self
     */
    public static function valueMustBePositive(string $field, float $value): self
    {
        return new self(
            sprintf('Field "%s" must be a positive number. Got: %s', $field, $value),
            400,
            null,
            ['field' => $field, 'value' => $value]
        );
    }

    /**
     * Create exception for empty items array.
     *
     * @return self
     */
    public static function emptyItemsArray(): self
    {
        return new self(
            'Items array cannot be empty.',
            400
        );
    }

    /**
     * Create exception for invalid items count (select_item requires exactly 1).
     *
     * @param int $count
     * @return self
     */
    public static function invalidItemsCount(int $count): self
    {
        return new self(
            sprintf('Items array must contain exactly 1 item for select_item event. Got: %d', $count),
            400,
            null,
            ['items_count' => $count]
        );
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Set validation errors.
     *
     * @param array<string, string> $errors
     * @return self
     */
    public function setErrors(array $errors): self
    {
        $this->errors = $errors;
        return $this;
    }
}
