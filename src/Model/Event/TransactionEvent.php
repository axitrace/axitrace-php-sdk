<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\ClientIdentity;
use AxiTrace\Model\Money;
use AxiTrace\Model\Product;

/**
 * Transaction event.
 *
 * Tracks completed purchases/orders.
 */
class TransactionEvent extends AbstractEvent
{
    public const SOURCE_WEB_DESKTOP = 'WEB_DESKTOP';
    public const SOURCE_WEB_MOBILE = 'WEB_MOBILE';
    public const SOURCE_MOBILE_APP = 'MOBILE_APP';
    public const SOURCE_POS = 'POS';
    public const SOURCE_MOBILE = 'MOBILE';
    public const SOURCE_DESKTOP = 'DESKTOP';

    /**
     * @var string
     */
    private string $orderId;

    /**
     * @var string
     */
    private string $source;

    /**
     * @var Money
     */
    private Money $revenue;

    /**
     * @var Money
     */
    private Money $value;

    /**
     * @var string
     */
    private string $paymentMethod;

    /**
     * @var ClientIdentity
     */
    private ClientIdentity $client;

    /**
     * @var array<array<string, mixed>>
     */
    private array $products = [];

    /**
     * @var Money|null
     */
    private ?Money $discountAmount = null;

    /**
     * @var array<string, mixed>
     */
    private array $metadata = [];

    /**
     * @var string|null
     */
    private ?string $eventSalt = null;

    /**
     * @var string|null Facebook pixel ID
     */
    private ?string $fbp = null;

    /**
     * @var string|null Facebook click ID
     */
    private ?string $fbc = null;

    /**
     * Page URL for event_source_url in Facebook CAPI.
     * This should be a URL that matches a verified domain in your Facebook pixel settings.
     *
     * @var string|null
     */
    private ?string $url = null;

    /**
     * When the order was placed, if it is sent later (from a queue, a cron re-drive or a
     * backfill). Null means "now": the API stamps the transaction with its receive time.
     */
    private ?\DateTimeImmutable $recordedAt = null;

    /**
     * Total tax of the order, a plain number in the revenue currency. Null: not sent.
     */
    private ?float $tax = null;

    /**
     * Shipping charged to the buyer, gross of tax, in the revenue currency. Null: not sent.
     */
    private ?float $shipping = null;

    /**
     * Whether the revenue and product prices include tax. Null: not sent.
     */
    private ?bool $taxesIncluded = null;

    /**
     * Order-level costs the merchant pays (profit tracking), keyed by the API field name
     * (shipping, paymentFee, handling). Empty: no "costs" object is sent.
     *
     * @var array<string, Money>
     */
    private array $costs = [];

    /**
     * @param string $orderId
     * @param string $source
     * @param Money $revenue
     * @param Money $value
     * @param string $paymentMethod
     */
    public function __construct(
        string $orderId,
        string $source,
        Money $revenue,
        Money $value,
        string $paymentMethod
    ) {
        $this->orderId = $orderId;
        $this->source = $source;
        $this->revenue = $revenue;
        $this->value = $value;
        $this->paymentMethod = $paymentMethod;
        $this->client = new ClientIdentity();
    }

    /**
     * Create with simple values.
     *
     * @param string $orderId
     * @param float $revenue
     * @param float $value
     * @param string $currency
     * @param string $paymentMethod
     * @param string $source
     * @return self
     */
    public static function create(
        string $orderId,
        float $revenue,
        float $value,
        string $currency,
        string $paymentMethod,
        string $source = self::SOURCE_WEB_DESKTOP
    ): self {
        return new self(
            $orderId,
            $source,
            new Money($revenue, $currency),
            new Money($value, $currency),
            $paymentMethod
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/transaction';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'transaction';
    }

    /**
     * Set client identity.
     *
     * @param ClientIdentity $client
     * @return self
     */
    public function setClient(ClientIdentity $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Set client custom ID (visitor ID).
     *
     * @param string $customId
     * @return self
     */
    public function setClientCustomId(string $customId): self
    {
        $this->client->setCustomId($customId);
        return $this;
    }

    /**
     * Set client email.
     *
     * @param string $email
     * @return self
     */
    public function setClientEmail(string $email): self
    {
        $this->client->setEmail($email);
        return $this;
    }

    /**
     * Set client phone number (E.164 recommended, e.g. +14155552671).
     * Unlike AbstractEvent::setPhone(), this is stored on the client identity object,
     * which is what the ingestion API reads for CAPI/CRM matching.
     *
     * @param string $phone
     * @return self
     */
    public function setClientPhone(string $phone): self
    {
        $this->client->setPhone($phone);
        return $this;
    }

    /**
     * Set the buyer's name and postal address on the client identity.
     *
     * These are match keys, not decoration: Meta CAPI hashes them into fn/ln/ct/st/zp/country
     * and TikTok into first_name/last_name/city/state/zip_code/country. Pass plain text —
     * hashing happens server-side. Empty and whitespace-only values are ignored so a partly
     * filled checkout form never sends blank match keys.
     *
     * @param string|null $firstName
     * @param string|null $lastName
     * @param string|null $city
     * @param string|null $state State, province or region
     * @param string|null $zip
     * @param string|null $country ISO 3166-1 alpha-2 preferred (e.g. "CH")
     * @return self
     */
    public function setClientAddress(
        ?string $firstName = null,
        ?string $lastName = null,
        ?string $city = null,
        ?string $state = null,
        ?string $zip = null,
        ?string $country = null
    ): self {
        if (($value = self::cleanClientField($firstName)) !== null) {
            $this->client->setFirstName($value);
        }
        if (($value = self::cleanClientField($lastName)) !== null) {
            $this->client->setLastName($value);
        }
        if (($value = self::cleanClientField($city)) !== null) {
            $this->client->setCity($value);
        }
        if (($value = self::cleanClientField($state)) !== null) {
            $this->client->setState($value);
        }
        if (($value = self::cleanClientField($zip)) !== null) {
            $this->client->setZip($value);
        }
        if (($value = self::cleanClientField($country)) !== null) {
            $this->client->setCountry($value);
        }

        return $this;
    }

    /**
     * Trim a client match key, returning null for anything that carries no signal.
     *
     * @param string|null $value
     * @return string|null
     */
    private static function cleanClientField(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Add a product to the transaction.
     *
     * Accepts either an AxiTrace\Model\Product instance or a plain array. The entry is
     * normalized immediately (see normalizeProduct()) so malformed shapes fail fast in
     * PHP instead of producing an opaque 400 from the API.
     *
     * @param array<string, mixed>|Product $product
     * @return self
     * @throws ValidationException If the product cannot be normalized.
     */
    public function addProduct($product): self
    {
        $this->products[] = $this->normalizeProduct($product);
        return $this;
    }

    /**
     * Set products.
     *
     * Each entry is normalized the same way as addProduct() — see normalizeProduct().
     *
     * @param array<int, array<string, mixed>|Product> $products
     * @return self
     * @throws ValidationException If any product cannot be normalized.
     */
    public function setProducts(array $products): self
    {
        $this->products = [];
        foreach ($products as $product) {
            $this->products[] = $this->normalizeProduct($product);
        }
        return $this;
    }

    /**
     * Normalize a single product entry into the shape the /v1/transaction endpoint expects.
     *
     * Fixes the two most common integration mistakes that otherwise reach the API as an
     * opaque "Invalid JSON" 400:
     * - a scalar finalUnitPrice (e.g. 89.99, the natural thing to write) is wrapped into
     *   {amount: 89.99, currency: <transaction currency>}
     * - a numeric-string quantity (e.g. "2", common when values come from $_POST/CSV) is
     *   cast to an int
     *
     * A Product model instance is converted using its sku (falling back to item ID), name,
     * price and quantity. Throws immediately - before the event is ever sent - if a value
     * cannot be safely coerced.
     *
     * @param array<string, mixed>|Product $product
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function normalizeProduct($product): array
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

            $product = $fromModel;
        }

        if (!is_array($product)) {
            throw new ValidationException(
                sprintf(
                    'Invalid transaction product: expected an array or an %s instance, got %s. '
                    . 'Example: ["sku" => "SKU-001", "name" => "Product 1", '
                    . '"finalUnitPrice" => 89.99, "quantity" => 1].',
                    Product::class,
                    is_object($product) ? get_class($product) : gettype($product)
                ),
                400
            );
        }

        if (array_key_exists('finalUnitPrice', $product)) {
            $product['finalUnitPrice'] = $this->normalizeFinalUnitPrice($product['finalUnitPrice']);
        }

        if (
            array_key_exists('quantity', $product)
            && is_string($product['quantity'])
            && is_numeric($product['quantity'])
        ) {
            $product['quantity'] = (int) $product['quantity'];
        }

        if (array_key_exists('unitCost', $product)) {
            $unitCost = $this->toCostMoney($product['unitCost'], 'products[].unitCost');
            $product['unitCost'] = $unitCost->toArray();
        }

        if (array_key_exists('externalId', $product)) {
            $externalId = $product['externalId'];
            if (is_int($externalId)) {
                $externalId = (string) $externalId;
            }
            if (!is_string($externalId) || trim($externalId) === '') {
                throw new ValidationException(
                    sprintf(
                        'Invalid products[].externalId: expected a non-empty string, got %s. '
                        . 'Correct example: "externalId" => "woocommerce:1234".',
                        is_string($externalId) ? '""' : gettype($externalId)
                    ),
                    400
                );
            }
            $product['externalId'] = $externalId;
        }

        return $product;
    }

    /**
     * Read a cost value (a number, an ["amount", "currency"] array or a Money instance)
     * as Money in the revenue currency.
     *
     * Costs are compared with revenue to compute profit, so a cost in another currency
     * or a negative cost is rejected here, before anything is sent.
     *
     * @param mixed $value
     * @param string $field Field name used in the error message
     * @return Money
     * @throws ValidationException
     */
    private function toCostMoney($value, string $field): Money
    {
        $currency = $this->revenue->getCurrency();

        if ($value instanceof Money) {
            $money = $value;
        } elseif (is_array($value)) {
            if (!array_key_exists('amount', $value) || !is_numeric($value['amount'])) {
                throw new ValidationException(
                    sprintf(
                        'Invalid %s: array form requires a numeric "amount" key. Received: %s. '
                        . 'Correct example: ["amount" => 12.50, "currency" => "%s"].',
                        $field,
                        json_encode($value),
                        $currency
                    ),
                    400
                );
            }
            $valueCurrency = isset($value['currency']) && is_string($value['currency']) && $value['currency'] !== ''
                ? $value['currency']
                : $currency;
            $money = new Money((float) $value['amount'], $valueCurrency);
        } elseif (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $money = new Money((float) $value, $currency);
        } else {
            throw new ValidationException(
                sprintf(
                    'Invalid %s: expected a number (e.g. 12.50), ["amount" => 12.50, "currency" => "%s"] '
                    . 'or a %s instance, got %s.',
                    $field,
                    $currency,
                    Money::class,
                    is_object($value) ? get_class($value) : gettype($value)
                ),
                400
            );
        }

        if ($money->getCurrency() !== $currency) {
            throw new ValidationException(
                sprintf(
                    'Invalid %s: the currency %s differs from the revenue currency %s. '
                    . 'Send costs in the same currency as the revenue.',
                    $field,
                    $money->getCurrency(),
                    $currency
                ),
                400
            );
        }

        self::assertNonNegative($money->getAmount(), $field);

        return $money;
    }

    /**
     * @param float $amount
     * @param string $field
     * @throws ValidationException
     */
    private static function assertNonNegative(float $amount, string $field): void
    {
        if ($amount < 0 || is_nan($amount) || is_infinite($amount)) {
            throw new ValidationException(
                sprintf('Invalid %s: expected a number of at least 0, got %s.', $field, (string) $amount),
                400
            );
        }
    }

    /**
     * Normalize a products[].finalUnitPrice value into {amount: float, currency: string}.
     *
     * Accepts a bare number (the natural thing to write) or an already-shaped
     * ["amount" => ..., "currency" => ...] array. When currency is omitted it defaults to
     * the transaction's own currency.
     *
     * @param mixed $value
     * @return array<string, mixed>
     * @throws ValidationException
     */
    private function normalizeFinalUnitPrice($value): array
    {
        if (is_array($value)) {
            if (!array_key_exists('amount', $value) || !is_numeric($value['amount'])) {
                throw new ValidationException(
                    sprintf(
                        'Invalid products[].finalUnitPrice: array form requires a numeric "amount" key. '
                        . 'Received: %s. Correct example: ["amount" => 89.99, "currency" => "USD"].',
                        json_encode($value)
                    ),
                    400
                );
            }

            $currency = $this->value->getCurrency();
            if (isset($value['currency']) && is_string($value['currency']) && $value['currency'] !== '') {
                $currency = strtoupper($value['currency']);
            }

            return ['amount' => (float) $value['amount'], 'currency' => $currency];
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            return ['amount' => (float) $value, 'currency' => $this->value->getCurrency()];
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
     * Set the total tax of the order, in the revenue currency.
     *
     * @param float $tax
     * @return self
     * @throws ValidationException If the amount is negative.
     */
    public function setTax(float $tax): self
    {
        self::assertNonNegative($tax, 'tax');
        $this->tax = $tax;
        return $this;
    }

    /**
     * Set the shipping charged to the buyer, gross of tax, in the revenue currency.
     *
     * This is what the buyer paid for delivery, not what delivery cost you: your own
     * shipping cost goes into setCosts().
     *
     * @param float $shipping
     * @return self
     * @throws ValidationException If the amount is negative.
     */
    public function setShipping(float $shipping): self
    {
        self::assertNonNegative($shipping, 'shipping');
        $this->shipping = $shipping;
        return $this;
    }

    /**
     * Set whether the revenue and product prices include tax.
     *
     * @param bool $taxesIncluded
     * @return self
     */
    public function setTaxesIncluded(bool $taxesIncluded): self
    {
        $this->taxesIncluded = $taxesIncluded;
        return $this;
    }

    /**
     * Set the costs you paid for this order, used by profit tracking.
     *
     * Each value is a number in the revenue currency, an ["amount", "currency"] array or a
     * Money instance; pass null for a cost you do not know, so the workspace rule applies
     * to it. A cost in a currency other than the revenue currency, or a negative cost, is
     * rejected before sending. Calling it again replaces every cost set before.
     *
     * Costs are accepted only with your secret key, which this SDK always uses; never
     * expose them in a browser.
     *
     * @param Money|float|int|array<string, mixed>|null $shipping What delivery cost you
     * @param Money|float|int|array<string, mixed>|null $paymentFee The payment provider fee
     * @param Money|float|int|array<string, mixed>|null $handling Packing and handling cost
     * @return self
     * @throws ValidationException
     */
    public function setCosts($shipping = null, $paymentFee = null, $handling = null): self
    {
        $costs = [];
        foreach (['shipping' => $shipping, 'paymentFee' => $paymentFee, 'handling' => $handling] as $key => $value) {
            if ($value !== null) {
                $costs[$key] = $this->toCostMoney($value, 'costs.' . $key);
            }
        }

        $this->costs = $costs;
        return $this;
    }

    /**
     * Set discount amount.
     *
     * @param Money $discountAmount
     * @return self
     */
    public function setDiscountAmount(Money $discountAmount): self
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    /**
     * Set metadata.
     *
     * @param array<string, mixed> $metadata
     * @return self
     */
    public function setMetadata(array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Set event salt for deduplication.
     *
     * @param string $eventSalt
     * @return self
     */
    public function setEventSalt(string $eventSalt): self
    {
        $this->eventSalt = $eventSalt;
        return $this;
    }

    /**
     * Set session ID for cross-SDK profile matching.
     *
     * @param string $sessionId
     * @return self
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Set Facebook pixel ID (_fbp cookie).
     *
     * @param string $fbp
     * @return self
     */
    public function setFbp(string $fbp): self
    {
        $this->fbp = $fbp;
        return $this;
    }

    /**
     * Set Facebook click ID (_fbc cookie).
     *
     * @param string $fbc
     * @return self
     */
    public function setFbc(string $fbc): self
    {
        $this->fbc = $fbc;
        return $this;
    }

    /**
     * Set page URL for event_source_url in Facebook CAPI.
     * This URL should match a verified domain in your Facebook pixel settings.
     *
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * Set when the order was actually placed.
     *
     * Use it whenever the transaction is sent later than it happened - from a queue, a
     * cron job that re-sends failed deliveries, or a backfill. Without it AxiTrace dates
     * the order at the moment the request arrives, so a purchase re-sent three days late
     * lands on the wrong day in reports and reaches the ad platforms as a new conversion.
     *
     * The time is sent in UTC with millisecond precision. A time in the future is
     * replaced by the server's clock. Meta accepts conversions up to 7 days old; an older
     * transaction is still recorded in AxiTrace and forwarded to platforms with longer
     * windows (Google Ads accepts 90 days).
     *
     * @param \DateTimeInterface $recordedAt
     * @return self
     */
    public function setRecordedAt(\DateTimeInterface $recordedAt): self
    {
        $this->recordedAt = (new \DateTimeImmutable('@' . $recordedAt->format('U.u')))
            ->setTimezone(new \DateTimeZone('UTC'));
        return $this;
    }

    /**
     * When the order was placed, in UTC, or null when the API should use its receive time.
     *
     * @return \DateTimeImmutable|null
     */
    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /**
     * Get page URL.
     *
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        if (!$this->client->hasIdentifier()) {
            throw ValidationException::missingUserIdentifier();
        }

        if (empty($this->orderId)) {
            throw ValidationException::missingRequiredField('orderId', 'transaction');
        }

        if (empty($this->paymentMethod)) {
            throw ValidationException::missingRequiredField('paymentInfo.method', 'transaction');
        }

        $validSources = [
            self::SOURCE_WEB_DESKTOP,
            self::SOURCE_WEB_MOBILE,
            self::SOURCE_MOBILE_APP,
            self::SOURCE_POS,
            self::SOURCE_MOBILE,
            self::SOURCE_DESKTOP,
        ];

        if (!in_array($this->source, $validSources, true)) {
            throw new ValidationException(
                sprintf('Invalid source: %s. Valid sources: %s', $this->source, implode(', ', $validSources)),
                400
            );
        }

        if (empty($this->products)) {
            throw ValidationException::emptyItemsArray();
        }

        // Validate products. Note: addProduct()/setProducts() already normalize each entry
        // (scalar finalUnitPrice -> {amount, currency}, numeric-string quantity -> int), so
        // these checks are a last-resort guard against malformed shapes before any HTTP call.
        foreach ($this->products as $i => $product) {
            if (empty($product['sku'])) {
                throw ValidationException::missingRequiredField("products[$i].sku", 'transaction');
            }
            if (empty($product['name'])) {
                throw ValidationException::missingRequiredField("products[$i].name", 'transaction');
            }
            if (array_key_exists('finalUnitPrice', $product)) {
                $price = $product['finalUnitPrice'];
                if (
                    !is_array($price)
                    || !isset($price['amount']) || !is_numeric($price['amount'])
                    || !isset($price['currency']) || !is_string($price['currency'])
                ) {
                    throw new ValidationException(
                        sprintf(
                            'Invalid products[%d].finalUnitPrice: expected {"amount": <number>, '
                            . '"currency": "USD"}, got %s. Correct example: '
                            . '"finalUnitPrice" => ["amount" => 89.99, "currency" => "USD"].',
                            $i,
                            json_encode($price)
                        ),
                        400
                    );
                }
            }
            if (array_key_exists('quantity', $product) && !is_int($product['quantity'])) {
                throw new ValidationException(
                    sprintf(
                        'Invalid products[%d].quantity: expected an int, got %s. Correct example: '
                        . '"quantity" => 2 (not "2").',
                        $i,
                        gettype($product['quantity'])
                    ),
                    400
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = [
            'client' => $this->client->toArray(),
            'orderId' => $this->orderId,
            'source' => $this->source,
            'revenue' => $this->revenue->toArray(),
            'value' => $this->value->toArray(),
            'paymentInfo' => [
                'method' => $this->paymentMethod,
            ],
            'products' => $this->products,
        ];

        // Session ID for cross-SDK profile matching (links PHP SDK to JS SDK profile)
        if ($this->sessionId !== null) {
            $data['sessionId'] = $this->sessionId;
        }

        // Facebook cookies for CAPI matching
        if ($this->fbp !== null) {
            $data['fbp'] = $this->fbp;
        }

        if ($this->fbc !== null) {
            $data['fbc'] = $this->fbc;
        }

        // URL for event_source_url in Facebook CAPI
        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        if ($this->discountAmount !== null) {
            $data['discountAmount'] = $this->discountAmount->toArray();
        }

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->eventSalt !== null) {
            $data['eventSalt'] = $this->eventSalt;
        }

        if ($this->recordedAt !== null) {
            $data['recordedAt'] = $this->recordedAt->format('Y-m-d\\TH:i:s.v\\Z');
        }

        // Profit tracking fields. Each is sent only when set, so a transaction built
        // without them serialises exactly as it did before they existed.
        if ($this->tax !== null) {
            $data['tax'] = $this->tax;
        }

        if ($this->shipping !== null) {
            $data['shipping'] = $this->shipping;
        }

        if ($this->taxesIncluded !== null) {
            $data['taxesIncluded'] = $this->taxesIncluded;
        }

        if ($this->costs !== []) {
            $data['costs'] = array_map(static function (Money $cost): array {
                return $cost->toArray();
            }, $this->costs);
        }

        // Include additional params (attribution data like fbclid, utm_source, etc.)
        // CRITICAL: This ensures server-side attribution data is sent with the event
        return $this->addParamsToArray($data);
    }
}
