<?php

declare(strict_types=1);

namespace AxiTrace\Model;

/**
 * Product/Item model for cart and catalog events.
 */
class Product
{
    /**
     * @var string
     */
    private string $itemId;

    /**
     * @var string|null
     */
    private ?string $itemName = null;

    /**
     * @var float|null
     */
    private ?float $price = null;

    /**
     * @var int|null
     */
    private ?int $quantity = null;

    /**
     * @var string|null
     */
    private ?string $itemCategory = null;

    /**
     * @var string|null
     */
    private ?string $itemBrand = null;

    /**
     * @var string|null
     */
    private ?string $itemVariant = null;

    /**
     * @var int|null
     */
    private ?int $index = null;

    /**
     * @var string|null
     */
    private ?string $currency = null;

    /**
     * @var string|null
     */
    private ?string $sku = null;

    /**
     * @var string|null
     */
    private ?string $url = null;

    /**
     * @var string|null
     */
    private ?string $image = null;

    /**
     * @var bool|null
     */
    private ?bool $inStock = null;

    /**
     * @var int|null
     */
    private ?int $stockQuantity = null;

    /**
     * @var array<string, mixed>
     */
    private array $customAttributes = [];

    /**
     * @param string $itemId
     */
    public function __construct(string $itemId)
    {
        $this->itemId = $itemId;
    }

    /**
     * Create a product from an array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $itemId = $data['item_id'] ?? $data['sku'] ?? $data['id'] ?? '';
        $product = new self((string) $itemId);

        if (isset($data['item_name']) || isset($data['name'])) {
            $product->setItemName($data['item_name'] ?? $data['name']);
        }

        if (isset($data['price'])) {
            $product->setPrice((float) $data['price']);
        }

        if (isset($data['quantity'])) {
            $product->setQuantity((int) $data['quantity']);
        }

        if (isset($data['item_category']) || isset($data['category'])) {
            $product->setItemCategory($data['item_category'] ?? $data['category']);
        }

        if (isset($data['item_brand']) || isset($data['brand'])) {
            $product->setItemBrand($data['item_brand'] ?? $data['brand']);
        }

        if (isset($data['item_variant']) || isset($data['variant'])) {
            $product->setItemVariant($data['item_variant'] ?? $data['variant']);
        }

        if (isset($data['index'])) {
            $product->setIndex((int) $data['index']);
        }

        if (isset($data['currency'])) {
            $product->setCurrency($data['currency']);
        }

        if (isset($data['sku'])) {
            $product->setSku($data['sku']);
        }

        if (isset($data['url'])) {
            $product->setUrl($data['url']);
        }

        if (isset($data['image'])) {
            $product->setImage($data['image']);
        }

        if (isset($data['in_stock']) || isset($data['inStock'])) {
            $product->setInStock((bool) ($data['in_stock'] ?? $data['inStock']));
        }

        if (isset($data['stock_quantity']) || isset($data['stockQuantity'])) {
            $product->setStockQuantity((int) ($data['stock_quantity'] ?? $data['stockQuantity']));
        }

        return $product;
    }

    /**
     * Convert product to array for API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'item_id' => $this->itemId,
        ];

        if ($this->itemName !== null) {
            $data['item_name'] = $this->itemName;
        }

        if ($this->price !== null) {
            $data['price'] = $this->price;
        }

        if ($this->quantity !== null) {
            $data['quantity'] = $this->quantity;
        }

        if ($this->itemCategory !== null) {
            $data['item_category'] = $this->itemCategory;
        }

        if ($this->itemBrand !== null) {
            $data['item_brand'] = $this->itemBrand;
        }

        if ($this->itemVariant !== null) {
            $data['item_variant'] = $this->itemVariant;
        }

        if ($this->index !== null) {
            $data['index'] = $this->index;
        }

        if ($this->currency !== null) {
            $data['currency'] = $this->currency;
        }

        if ($this->sku !== null) {
            $data['sku'] = $this->sku;
        }

        if ($this->url !== null) {
            $data['url'] = $this->url;
        }

        if ($this->image !== null) {
            $data['image'] = $this->image;
        }

        if ($this->inStock !== null) {
            $data['inStock'] = $this->inStock;
        }

        if ($this->stockQuantity !== null) {
            $data['stockQuantity'] = $this->stockQuantity;
        }

        // Add custom attributes
        foreach ($this->customAttributes as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    // Fluent setters

    /**
     * @param string $itemName
     * @return self
     */
    public function setItemName(string $itemName): self
    {
        $this->itemName = $itemName;
        return $this;
    }

    /**
     * @param float $price
     * @return self
     */
    public function setPrice(float $price): self
    {
        $this->price = $price;
        return $this;
    }

    /**
     * @param int $quantity
     * @return self
     */
    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    /**
     * @param string $category
     * @return self
     */
    public function setItemCategory(string $category): self
    {
        $this->itemCategory = $category;
        return $this;
    }

    /**
     * @param string $brand
     * @return self
     */
    public function setItemBrand(string $brand): self
    {
        $this->itemBrand = $brand;
        return $this;
    }

    /**
     * @param string $variant
     * @return self
     */
    public function setItemVariant(string $variant): self
    {
        $this->itemVariant = $variant;
        return $this;
    }

    /**
     * @param int $index
     * @return self
     */
    public function setIndex(int $index): self
    {
        $this->index = $index;
        return $this;
    }

    /**
     * @param string $currency
     * @return self
     */
    public function setCurrency(string $currency): self
    {
        $this->currency = strtoupper($currency);
        return $this;
    }

    /**
     * @param string $sku
     * @return self
     */
    public function setSku(string $sku): self
    {
        $this->sku = $sku;
        return $this;
    }

    /**
     * @param string $url
     * @return self
     */
    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param string $image
     * @return self
     */
    public function setImage(string $image): self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @param bool $inStock
     * @return self
     */
    public function setInStock(bool $inStock): self
    {
        $this->inStock = $inStock;
        return $this;
    }

    /**
     * @param int $stockQuantity
     * @return self
     */
    public function setStockQuantity(int $stockQuantity): self
    {
        $this->stockQuantity = $stockQuantity;
        return $this;
    }

    /**
     * Set a custom attribute.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function setCustomAttribute(string $key, $value): self
    {
        $this->customAttributes[$key] = $value;
        return $this;
    }

    // Getters

    /**
     * @return string
     */
    public function getItemId(): string
    {
        return $this->itemId;
    }

    /**
     * @return string|null
     */
    public function getItemName(): ?string
    {
        return $this->itemName;
    }

    /**
     * @return float|null
     */
    public function getPrice(): ?float
    {
        return $this->price;
    }

    /**
     * @return int|null
     */
    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    /**
     * @return string|null
     */
    public function getItemCategory(): ?string
    {
        return $this->itemCategory;
    }

    /**
     * @return string|null
     */
    public function getItemBrand(): ?string
    {
        return $this->itemBrand;
    }

    /**
     * @return string|null
     */
    public function getCurrency(): ?string
    {
        return $this->currency;
    }
}
