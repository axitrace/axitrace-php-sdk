<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;

/**
 * Add shipping info event.
 *
 * Tracks when a user adds shipping information during checkout.
 */
class AddShippingInfoEvent extends AbstractEvent
{
    /**
     * @var string
     */
    private string $currency;

    /**
     * @var float
     */
    private float $value;

    /**
     * @var Product[]
     */
    private array $items = [];

    /**
     * @var string|null
     */
    private ?string $shippingTier = null;

    /**
     * @var string|null
     */
    private ?string $coupon = null;

    /**
     * @param string $currency
     * @param float $value
     */
    public function __construct(string $currency, float $value)
    {
        $this->currency = strtoupper($currency);
        $this->value = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/checkout/add_shipping_info';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'add_shipping_info';
    }

    /**
     * Add a product.
     *
     * @param Product $product
     * @return self
     */
    public function addItem(Product $product): self
    {
        $this->items[] = $product;
        return $this;
    }

    /**
     * Set items from array.
     *
     * @param array<array<string, mixed>> $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = [];
        foreach ($items as $item) {
            if ($item instanceof Product) {
                $this->items[] = $item;
            } else {
                $this->items[] = Product::fromArray($item);
            }
        }
        return $this;
    }

    /**
     * Set shipping tier/method.
     *
     * @param string $shippingTier
     * @return self
     */
    public function setShippingTier(string $shippingTier): self
    {
        $this->shippingTier = $shippingTier;
        return $this;
    }

    /**
     * Set coupon code.
     *
     * @param string $coupon
     * @return self
     */
    public function setCoupon(string $coupon): self
    {
        $this->coupon = $coupon;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->currency)) {
            throw ValidationException::missingRequiredField('currency', 'add_shipping_info');
        }

        if ($this->value <= 0) {
            throw ValidationException::valueMustBePositive('value', $this->value);
        }

        if (empty($this->items)) {
            throw ValidationException::emptyItemsArray();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = $this->buildBaseArray();

        $data['currency'] = $this->currency;
        $data['value'] = $this->value;
        $data['items'] = array_map(function (Product $item) {
            return $item->toArray();
        }, $this->items);

        if ($this->shippingTier !== null) {
            $data['shipping_tier'] = $this->shippingTier;
        }

        if ($this->coupon !== null) {
            $data['coupon'] = $this->coupon;
        }

        return $this->addParamsToArray($data);
    }
}
