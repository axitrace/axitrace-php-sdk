<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * A refund or cancellation of an order sent earlier as a transaction.
 *
 * Sent to POST /v1/refund, which accepts only the secret key (the SDK always uses it).
 * A refund reduces the order's profit and POAS in AxiTrace; revenue and ROAS reports
 * are not changed. The endpoint deduplicates on refundId, so a retried call with the
 * same refundId is recorded once.
 *
 * The event carries no visitor identity: it refers to the order by its orderId.
 */
final class RefundEvent implements EventInterface
{
    public const ENDPOINT = '/v1/refund';

    private string $orderId;

    private string $refundId;

    private float $amount;

    private string $currency;

    private \DateTimeImmutable $refundedAt;

    private bool $isCancellation = false;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $lines = [];

    /**
     * @param string $orderId The orderId the transaction was sent with
     * @param string $refundId Your id of this refund, unique per order (the dedup key)
     * @param float $amount The total refunded to the buyer
     * @param string $currency ISO 4217 code, the currency of the original transaction
     * @param \DateTimeInterface|null $refundedAt When the refund happened; null means now
     */
    public function __construct(
        string $orderId,
        string $refundId,
        float $amount,
        string $currency,
        ?\DateTimeInterface $refundedAt = null
    ) {
        $this->orderId = $orderId;
        $this->refundId = $refundId;
        $this->amount = $amount;
        $this->currency = strtoupper($currency);
        $this->refundedAt = self::toUtc($refundedAt ?? new \DateTimeImmutable());
    }

    public function getEndpoint(): string
    {
        return self::ENDPOINT;
    }

    public function getAction(): string
    {
        return 'refund';
    }

    /**
     * Set when the refund happened (sent in UTC with millisecond precision).
     *
     * @param \DateTimeInterface $refundedAt
     * @return self
     */
    public function setRefundedAt(\DateTimeInterface $refundedAt): self
    {
        $this->refundedAt = self::toUtc($refundedAt);
        return $this;
    }

    /**
     * Mark the refund as a cancellation of the order (true) or a refund (false, default).
     *
     * @param bool $isCancellation
     * @return self
     */
    public function setCancellation(bool $isCancellation = true): self
    {
        $this->isCancellation = $isCancellation;
        return $this;
    }

    /**
     * Add a refunded order line. Identify the product by its sku, its externalId or both.
     *
     * Leave lines out for a refund that returns no products (for example a goodwill
     * refund or a shipping refund).
     *
     * @param int $quantity Units returned
     * @param float $amount Amount refunded for this line, in the refund currency
     * @param string|null $sku The sku the product was sent with in the transaction
     * @param string|null $externalId The product's id in your store platform
     * @return self
     */
    public function addLine(int $quantity, float $amount, ?string $sku = null, ?string $externalId = null): self
    {
        $line = [];

        if ($sku !== null && $sku !== '') {
            $line['sku'] = $sku;
        }

        if ($externalId !== null && $externalId !== '') {
            $line['externalId'] = $externalId;
        }

        $line['quantity'] = $quantity;
        $line['amount'] = $amount;

        $this->lines[] = $line;
        return $this;
    }

    /**
     * Replace the lines with arrays of the keys sku, externalId, quantity and amount.
     *
     * @param array<int, array<string, mixed>> $lines
     * @return self
     * @throws ValidationException If a line is not an array or has a non-numeric value.
     */
    public function setLines(array $lines): self
    {
        $this->lines = [];

        foreach ($lines as $i => $line) {
            if (!is_array($line)) {
                throw new ValidationException(
                    sprintf(
                        'Invalid refund lines[%s]: expected an array, got %s. Example: '
                        . '["sku" => "SKU-001", "quantity" => 1, "amount" => 49.99].',
                        (string) $i,
                        gettype($line)
                    ),
                    400
                );
            }

            $quantity = $line['quantity'] ?? null;
            $amount = $line['amount'] ?? null;
            if (!is_int($quantity) && !(is_string($quantity) && preg_match('/^-?\d+$/', $quantity) === 1)) {
                throw new ValidationException(
                    sprintf('Invalid refund lines[%s].quantity: expected a whole number.', (string) $i),
                    400
                );
            }
            if (!is_numeric($amount)) {
                throw new ValidationException(
                    sprintf('Invalid refund lines[%s].amount: expected a number.', (string) $i),
                    400
                );
            }

            $sku = $line['sku'] ?? null;
            $externalId = $line['externalId'] ?? $line['external_id'] ?? null;

            $this->addLine(
                (int) $quantity,
                (float) $amount,
                $sku === null ? null : (string) $sku,
                $externalId === null ? null : (string) $externalId
            );
        }

        return $this;
    }

    public function validate(): void
    {
        if (trim($this->orderId) === '') {
            throw ValidationException::missingRequiredField('orderId', 'refund');
        }

        if (trim($this->refundId) === '') {
            throw ValidationException::missingRequiredField('refundId', 'refund');
        }

        if (!preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new ValidationException(
                sprintf('The currency "%s" must be an ISO 4217 code (3 uppercase letters).', $this->currency),
                400
            );
        }

        self::assertNonNegative($this->amount, 'amount');

        foreach ($this->lines as $i => $line) {
            if (!isset($line['sku']) && !isset($line['externalId'])) {
                throw new ValidationException(
                    sprintf('Invalid refund lines[%d]: set a sku, an externalId or both.', $i),
                    400
                );
            }

            if ($line['quantity'] < 0) {
                throw new ValidationException(
                    sprintf('Invalid refund lines[%d].quantity: expected at least 0, got %d.', $i, $line['quantity']),
                    400
                );
            }

            self::assertNonNegative($line['amount'], sprintf('lines[%d].amount', $i));
        }
    }

    /**
     * The /v1/refund payload.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'orderId' => $this->orderId,
            'refundId' => $this->refundId,
            'refundedAt' => $this->refundedAt->format('Y-m-d\\TH:i:s.v\\Z'),
            'amount' => $this->amount,
            'currency' => $this->currency,
            'isCancellation' => $this->isCancellation,
            'lines' => $this->lines,
        ];
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getRefundId(): string
    {
        return $this->refundId;
    }

    public function getRefundedAt(): \DateTimeImmutable
    {
        return $this->refundedAt;
    }

    private static function toUtc(\DateTimeInterface $value): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('@' . $value->format('U.u')))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    /**
     * @throws ValidationException
     */
    private static function assertNonNegative(float $amount, string $field): void
    {
        if ($amount < 0 || is_nan($amount) || is_infinite($amount)) {
            throw new ValidationException(
                sprintf('Invalid refund %s: expected a number of at least 0, got %s.', $field, (string) $amount),
                400
            );
        }
    }
}
