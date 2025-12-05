<?php

declare(strict_types=1);

namespace AxiTrace\Model;

/**
 * Money model for transaction events.
 */
class Money
{
    /**
     * @var float
     */
    private float $amount;

    /**
     * @var string
     */
    private string $currency;

    /**
     * @param float $amount
     * @param string $currency
     */
    public function __construct(float $amount, string $currency)
    {
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
    }

    /**
     * Create from array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['amount'] ?? 0),
            (string) ($data['currency'] ?? 'USD')
        );
    }

    /**
     * Convert to array for API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    /**
     * @return float
     */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /**
     * @return string
     */
    public function getCurrency(): string
    {
        return $this->currency;
    }

    /**
     * Format as string.
     *
     * @return string
     */
    public function __toString(): string
    {
        return sprintf('%.2f %s', $this->amount, $this->currency);
    }
}
