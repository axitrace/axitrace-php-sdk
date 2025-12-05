<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model;

use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

class ProductTest extends TestCase
{
    public function testCreateWithItemId(): void
    {
        $product = new Product('SKU-123');

        $this->assertEquals('SKU-123', $product->getItemId());
        $this->assertNull($product->getItemName());
        $this->assertNull($product->getPrice());
    }

    public function testFluentSetters(): void
    {
        $product = (new Product('SKU-123'))
            ->setItemName('Test Product')
            ->setPrice(29.99)
            ->setQuantity(2)
            ->setItemCategory('Electronics')
            ->setItemBrand('TestBrand');

        $this->assertEquals('Test Product', $product->getItemName());
        $this->assertEquals(29.99, $product->getPrice());
        $this->assertEquals(2, $product->getQuantity());
        $this->assertEquals('Electronics', $product->getItemCategory());
        $this->assertEquals('TestBrand', $product->getItemBrand());
    }

    public function testToArray(): void
    {
        $product = (new Product('SKU-123'))
            ->setItemName('Test Product')
            ->setPrice(29.99)
            ->setQuantity(2)
            ->setItemCategory('Electronics')
            ->setItemBrand('TestBrand');

        $array = $product->toArray();

        $this->assertEquals('SKU-123', $array['item_id']);
        $this->assertEquals('Test Product', $array['item_name']);
        $this->assertEquals(29.99, $array['price']);
        $this->assertEquals(2, $array['quantity']);
        $this->assertEquals('Electronics', $array['item_category']);
        $this->assertEquals('TestBrand', $array['item_brand']);
    }

    public function testToArrayOmitsNullValues(): void
    {
        $product = new Product('SKU-123');
        $array = $product->toArray();

        $this->assertArrayHasKey('item_id', $array);
        $this->assertArrayNotHasKey('item_name', $array);
        $this->assertArrayNotHasKey('price', $array);
        $this->assertArrayNotHasKey('quantity', $array);
    }

    public function testFromArray(): void
    {
        $product = Product::fromArray([
            'item_id' => 'SKU-456',
            'item_name' => 'From Array Product',
            'price' => 49.99,
            'quantity' => 3,
            'item_category' => 'Books',
            'item_brand' => 'Publisher',
        ]);

        $this->assertEquals('SKU-456', $product->getItemId());
        $this->assertEquals('From Array Product', $product->getItemName());
        $this->assertEquals(49.99, $product->getPrice());
        $this->assertEquals(3, $product->getQuantity());
        $this->assertEquals('Books', $product->getItemCategory());
        $this->assertEquals('Publisher', $product->getItemBrand());
    }

    public function testFromArrayWithAlternateKeys(): void
    {
        $product = Product::fromArray([
            'sku' => 'SKU-789',
            'name' => 'Alternate Keys Product',
            'price' => 19.99,
            'category' => 'Clothing',
            'brand' => 'Fashion',
        ]);

        $this->assertEquals('SKU-789', $product->getItemId());
        $this->assertEquals('Alternate Keys Product', $product->getItemName());
        $this->assertEquals(19.99, $product->getPrice());
        $this->assertEquals('Clothing', $product->getItemCategory());
        $this->assertEquals('Fashion', $product->getItemBrand());
    }

    public function testCustomAttributes(): void
    {
        $product = (new Product('SKU-123'))
            ->setCustomAttribute('color', 'blue')
            ->setCustomAttribute('size', 'XL');

        $array = $product->toArray();

        $this->assertEquals('blue', $array['color']);
        $this->assertEquals('XL', $array['size']);
    }

    public function testSetCurrencyUppercased(): void
    {
        $product = (new Product('SKU-123'))
            ->setCurrency('usd');

        $array = $product->toArray();

        $this->assertEquals('USD', $array['currency']);
    }

    public function testSetIndex(): void
    {
        $product = (new Product('SKU-123'))
            ->setIndex(5);

        $array = $product->toArray();

        $this->assertEquals(5, $array['index']);
    }

    public function testSetUrl(): void
    {
        $product = (new Product('SKU-123'))
            ->setUrl('https://example.com/product/123');

        $array = $product->toArray();

        $this->assertEquals('https://example.com/product/123', $array['url']);
    }

    public function testSetImage(): void
    {
        $product = (new Product('SKU-123'))
            ->setImage('https://example.com/images/product.jpg');

        $array = $product->toArray();

        $this->assertEquals('https://example.com/images/product.jpg', $array['image']);
    }

    public function testSetInStock(): void
    {
        $product = (new Product('SKU-123'))
            ->setInStock(true);

        $array = $product->toArray();

        $this->assertTrue($array['inStock']);
    }

    public function testSetStockQuantity(): void
    {
        $product = (new Product('SKU-123'))
            ->setStockQuantity(100);

        $array = $product->toArray();

        $this->assertEquals(100, $array['stockQuantity']);
    }
}
