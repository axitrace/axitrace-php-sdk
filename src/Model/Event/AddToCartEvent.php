<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;

/**
 * Add to cart event.
 *
 * Tracks when a user adds items to their shopping cart.
 */
class AddToCartEvent extends AbstractEvent
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
        return '/v1/product/addToCart';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'add_to_cart';
    }

    /**
     * Add a product to the cart.
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
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->currency)) {
            throw ValidationException::missingRequiredField('currency', 'add_to_cart');
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
        // Build API-compatible structure with nested client object
        $data = [
            'label' => 'add_to_cart',
            'client' => $this->buildClientObject(),
        ];

        if ($this->sessionId !== null) {
            $data['sessionId'] = $this->sessionId;
        }

        // For single item, use the first item's details in params
        // For multiple items, include all in params
        if (count($this->items) === 1) {
            $item = $this->items[0];
            $params = [
                'sku' => $item->getItemId(),
                'quantity' => $item->getQuantity() ?? 1,
            ];

            if ($item->getItemName() !== null) {
                $params['name'] = $item->getItemName();
            }

            if ($item->getPrice() !== null) {
                $params['finalUnitPrice'] = [
                    'amount' => $item->getPrice(),
                    'currency' => $item->getCurrency() ?? $this->currency,
                ];
            }
        } else {
            // Multiple items - send as items array
            $params = [
                'currency' => $this->currency,
                'value' => $this->value,
                'items' => array_map(function (Product $item) {
                    return $item->toArray();
                }, $this->items),
            ];
        }

        // Merge additional params
        $params = array_merge($params, $this->params);

        $data['params'] = $params;

        return $data;
    }
}
