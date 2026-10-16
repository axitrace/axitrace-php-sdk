<?php

declare(strict_types=1);

namespace AxiTrace\Model;

use AxiTrace\Exception\ValidationException;

/**
 * Normalizes product lines into the /v1/transaction product shape.
 *
 * One component shared by every event that carries products, so a line means the same
 * thing on a transaction and on a lead event (form submit, subscribe, start trial,
 * custom event). Two modes:
 *
 * - strict (normalize()): used by TransactionEvent. A value that cannot be safely coerced
 *   throws before the event is sent.
 * - lenient (normalizeLeadProducts()): used by lead events. A line that is not a valid
 *   purchase line is dropped instead, so a bad product can never stop a lead. Only a
 *   programmer type error (neither an array nor a Product) throws, through fromInput().
 */
final class ProductNormalizer
{
    /**
     * Reason codes of a dropped lead line, shared with the ingestion API contract.
     */
    public const REASON_MISSING_SKU = 'missing_sku';
    public const REASON_MISSING_NAME = 'missing_name';
    public const REASON_MISSING_PRICE = 'missing_price';
    public const REASON_INVALID_QUANTITY = 'invalid_quantity';
    public const REASON_CURRENCY_MISMATCH = 'currency_mismatch';

    private function __construct()
    {
    }

    /**
     * Convert a product entry into a plain array, throwing for anything that is neither an
     * array nor a Product instance.
     *
     * A Product model instance is converted using its sku (falling back to item ID), name,
     * price, quantity (falling back to 1), unit cost and external ID.
     *
     * @param array<string, mixed>|Product|mixed $product
     * @param string $context Event name used in the error message (e.g. "transaction")
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function fromInput($product, string $context): array
    {
        if ($product instanceof Product) {
            $fromModel = [
                'sku' => $product->getSku() ?? $product->getItemId(),
                'name' => $product->getItemName(),
                'quantity' => $product->getQuantity() ?? 1,
            ];

            if ($product->getPrice() !== null) {
                $fromModel['finalUnitPrice'] = $product->getPrice();
            }

            if ($product->getUnitCost() !== null) {
                $fromModel['unitCost'] = $product->getUnitCost();
            }

            if ($product->getExternalId() !== null) {
                $fromModel['externalId'] = $product->getExternalId();
            }

            return $fromModel;
        }

        if (!is_array($product)) {
            throw new ValidationException(
                sprintf(
                    'Invalid %s product: expected an array or an %s instance, got %s. '
                    . 'Example: ["sku" => "SKU-001", "name" => "Product 1", '
                    . '"finalUnitPrice" => 89.99, "quantity" => 1].',
                    $context,
                    Product::class,
                    is_object($product) ? get_class($product) : gettype($product)
                ),
                400
            );
        }

        return $product;
    }

    /**
     * Strict normalization of one product entry (transactions).
     *
     * Fixes the two most common integration mistakes that otherwise reach the API as an
     * opaque "Invalid JSON" 400:
     * - a scalar finalUnitPrice (e.g. 89.99) is wrapped into
     *   {amount: 89.99, currency: <$priceCurrency>}
     * - a numeric-string quantity (e.g. "2", common with $_POST/CSV values) is cast to int
     *
     * Throws immediately - before the event is ever sent - if a value cannot be safely
     * coerced.
     *
     * @param array<string, mixed>|Product|mixed $product
     * @param string $priceCurrency Currency a price without its own currency is given
     * @param callable $unitCostNormalizer Maps a unitCost input to its wire array; throws
     *                                     ValidationException when it is invalid
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function normalize($product, string $priceCurrency, callable $unitCostNormalizer): array
    {
        $product = self::fromInput($product, 'transaction');

        if (array_key_exists('finalUnitPrice', $product)) {
            $product['finalUnitPrice'] = self::normalizeFinalUnitPrice($product['finalUnitPrice'], $priceCurrency);
        }

        $product = self::coerceNumericStringQuantity($product);

        if (array_key_exists('unitCost', $product)) {
            $product['unitCost'] = $unitCostNormalizer($product['unitCost']);
        }

        if (array_key_exists('externalId', $product)) {
            $externalId = self::readExternalId($product['externalId']);
            if ($externalId === null) {
                $raw = $product['externalId'];
                throw new ValidationException(
                    sprintf(
                        'Invalid products[].externalId: expected a non-empty string, got %s. '
                        . 'Correct example: "externalId" => "woocommerce:1234".',
                        is_string($raw) ? '""' : gettype($raw)
                    ),
                    400
                );
            }
            $product['externalId'] = $externalId;
        }

        return $product;
    }

    /**
     * Normalize a products[].finalUnitPrice value into {amount: float, currency: string}.
     *
     * Accepts a bare number or an already-shaped ["amount" => ..., "currency" => ...] array.
     * When currency is omitted it defaults to $defaultCurrency.
     *
     * @param mixed $value
     * @param string $defaultCurrency
     * @return array<string, mixed>
     * @throws ValidationException
     */
    public static function normalizeFinalUnitPrice($value, string $defaultCurrency): array
    {
        $price = self::readPrice($value);

        if ($price !== null) {
            return ['amount' => $price['amount'], 'currency' => $price['currency'] ?? $defaultCurrency];
        }

        if (is_array($value)) {
            throw new ValidationException(
                sprintf(
                    'Invalid products[].finalUnitPrice: array form requires a numeric "amount" key. '
                    . 'Received: %s. Correct example: ["amount" => 89.99, "currency" => "USD"].',
                    json_encode($value)
                ),
                400
            );
        }

        throw new ValidationException(
            sprintf(
                'Invalid products[].finalUnitPrice: expected a number (e.g. 89.99) or '
                . '["amount" => 89.99, "currency" => "USD"], got %s. '
                . 'Correct example: "finalUnitPrice" => 89.99.',
                is_object($value) ? get_class($value) : gettype($value)
            ),
            400
        );
    }

    /**
     * Lenient normalization of the product lines of a lead event.
     *
     * A line is kept only when it is a valid purchase line: non-empty sku and name, a
     * readable finalUnitPrice and an integer quantity above 0 (a numeric-string quantity is
     * cast as for transactions). Every kept line shares one currency: the event value
     * currency when one is set, otherwise the currency of the first valid line that states
     * one. A scalar price takes that currency; a line in another currency, or a scalar price
     * when no currency is known at all, is dropped. unitCost is never part of a lead and is
     * removed; an invalid optional externalId is removed while the line is kept.
     *
     * Never throws: an invalid line is dropped.
     *
     * @param array<int, array<string, mixed>> $lines Entries already passed through fromInput()
     * @param string|null $valueCurrency The event value currency, if any
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeLeadProducts(array $lines, ?string $valueCurrency): array
    {
        $candidates = [];
        foreach ($lines as $line) {
            $candidate = self::readLeadLine($line);
            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        $currency = $valueCurrency !== null && $valueCurrency !== '' ? strtoupper($valueCurrency) : null;
        if ($currency === null) {
            foreach ($candidates as $candidate) {
                if ($candidate['price']['currency'] !== null) {
                    $currency = $candidate['price']['currency'];
                    break;
                }
            }
        }

        if ($currency === null) {
            return [];
        }

        $kept = [];
        foreach ($candidates as $candidate) {
            $lineCurrency = $candidate['price']['currency'] ?? $currency;
            if ($lineCurrency !== $currency) {
                continue;
            }

            $line = $candidate['line'];
            $line['finalUnitPrice'] = ['amount' => $candidate['price']['amount'], 'currency' => $lineCurrency];
            $kept[] = $line;
        }

        return $kept;
    }

    /**
     * Check one lead line against the purchase-line rules, currency aside.
     *
     * @param array<string, mixed> $line
     * @return array{line: array<string, mixed>, price: array{amount: float, currency: string|null}}|null
     */
    private static function readLeadLine(array $line): ?array
    {
        unset($line['unitCost']);

        $sku = self::readRequiredText($line, 'sku');
        $name = self::readRequiredText($line, 'name');
        $price = array_key_exists('finalUnitPrice', $line) ? self::readPrice($line['finalUnitPrice']) : null;
        $line = self::coerceNumericStringQuantity($line);

        if (
            $sku === null
            || $name === null
            || $price === null
            || !array_key_exists('quantity', $line)
            || !is_int($line['quantity'])
            || $line['quantity'] <= 0
        ) {
            return null;
        }

        $line['sku'] = $sku;
        $line['name'] = $name;

        if (array_key_exists('externalId', $line)) {
            $externalId = self::readExternalId($line['externalId']);
            if ($externalId === null) {
                unset($line['externalId']);
            } else {
                $line['externalId'] = $externalId;
            }
        }

        return ['line' => $line, 'price' => $price];
    }

    /**
     * @param array<string, mixed> $line
     * @param string $key
     * @return string|null
     */
    private static function readRequiredText(array $line, string $key): ?string
    {
        if (!array_key_exists($key, $line)) {
            return null;
        }

        $value = $line[$key];
        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        return $value;
    }

    /**
     * Read a price as an amount plus the currency it states, or null when it is unreadable.
     *
     * @param mixed $value
     * @return array{amount: float, currency: string|null}|null
     */
    private static function readPrice($value): ?array
    {
        if (is_array($value)) {
            if (!array_key_exists('amount', $value) || !is_numeric($value['amount'])) {
                return null;
            }

            $currency = null;
            if (isset($value['currency']) && is_string($value['currency']) && $value['currency'] !== '') {
                $currency = strtoupper($value['currency']);
            }

            return ['amount' => (float) $value['amount'], 'currency' => $currency];
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return ['amount' => (float) $value, 'currency' => null];
        }

        return null;
    }

    /**
     * @param array<string, mixed> $product
     * @return array<string, mixed>
     */
    private static function coerceNumericStringQuantity(array $product): array
    {
        if (
            array_key_exists('quantity', $product)
            && is_string($product['quantity'])
            && is_numeric($product['quantity'])
        ) {
            $product['quantity'] = (int) $product['quantity'];
        }

        return $product;
    }

    /**
     * @param mixed $externalId
     * @return string|null The external ID as a string, or null when it is not a non-empty string
     */
    private static function readExternalId($externalId): ?string
    {
        if (is_int($externalId)) {
            $externalId = (string) $externalId;
        }

        if (!is_string($externalId) || trim($externalId) === '') {
            return null;
        }

        return $externalId;
    }
}
