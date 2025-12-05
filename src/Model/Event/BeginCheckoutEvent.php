<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;

/**
 * Begin checkout event.
 *
 * Tracks when a user initiates the checkout process.
 */
class BeginCheckoutEvent extends AbstractEvent
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
        return '/v1/checkout/begin';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'begin_checkout';
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
            throw ValidationException::missingRequiredField('currency', 'begin_checkout');
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

        if ($this->coupon !== null) {
            $data['coupon'] = $this->coupon;
        }

        return $this->addParamsToArray($data);
    }
}
