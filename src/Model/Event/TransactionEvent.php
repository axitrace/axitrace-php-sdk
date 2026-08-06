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

        return $product;
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

        // Include additional params (attribution data like fbclid, utm_source, etc.)
        // CRITICAL: This ensures server-side attribution data is sent with the event
        return $this->addParamsToArray($data);
    }
}
