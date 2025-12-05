<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;

/**
 * Product view event.
 *
 * Tracks when a user views a product detail page.
 */
class ProductViewEvent extends AbstractEvent
{
    /**
     * @var Product
     */
    private Product $product;

    /**
     * @param Product $product
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Create from product data array.
     *
     * @param array<string, mixed> $productData
     * @return self
     */
    public static function fromArray(array $productData): self
    {
        return new self(Product::fromArray($productData));
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/product/view';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'product_view';
    }

    /**
     * Get the product.
     *
     * @return Product
     */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->product->getItemId())) {
            throw ValidationException::missingRequiredField('product.item_id', 'product_view');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // Build API-compatible structure with nested client object
        $data = [
            'label' => 'product_view',
            'client' => $this->buildClientObject(),
        ];

        if ($this->sessionId !== null) {
            $data['sessionId'] = $this->sessionId;
        }

        // Build params with product data
        $params = [
            'sku' => $this->product->getItemId(),
        ];

        if ($this->product->getItemName() !== null) {
            $params['name'] = $this->product->getItemName();
        }

        if ($this->product->getPrice() !== null) {
            $params['price'] = $this->product->getPrice();
        }

        if ($this->product->getCurrency() !== null) {
            $params['currency'] = $this->product->getCurrency();
        }

        if ($this->product->getItemCategory() !== null) {
            $params['category'] = $this->product->getItemCategory();
        }

        if ($this->product->getItemBrand() !== null) {
            $params['brand'] = $this->product->getItemBrand();
        }

        // Merge additional params
        $params = array_merge($params, $this->params);

        $data['params'] = $params;

        return $data;
    }
}
