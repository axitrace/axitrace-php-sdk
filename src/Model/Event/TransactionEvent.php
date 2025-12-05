<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\ClientIdentity;
use AxiTrace\Model\Money;

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
     * Add a product to the transaction.
     *
     * @param array<string, mixed> $product
     * @return self
     */
    public function addProduct(array $product): self
    {
        $this->products[] = $product;
        return $this;
    }

    /**
     * Set products.
     *
     * @param array<array<string, mixed>> $products
     * @return self
     */
    public function setProducts(array $products): self
    {
        $this->products = $products;
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

        // Validate products
        foreach ($this->products as $i => $product) {
            if (empty($product['sku'])) {
                throw ValidationException::missingRequiredField("products[$i].sku", 'transaction');
            }
            if (empty($product['name'])) {
                throw ValidationException::missingRequiredField("products[$i].name", 'transaction');
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

        if ($this->discountAmount !== null) {
            $data['discountAmount'] = $this->discountAmount->toArray();
        }

        if (!empty($this->metadata)) {
            $data['metadata'] = $this->metadata;
        }

        if ($this->eventSalt !== null) {
            $data['eventSalt'] = $this->eventSalt;
        }

        return $data;
    }
}
