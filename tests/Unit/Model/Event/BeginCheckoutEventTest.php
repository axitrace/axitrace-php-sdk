<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

class BeginCheckoutEventTest extends TestCase
{
    public function testGetEndpoint(): void
    {
        $event = new BeginCheckoutEvent('USD', 99.99);

        $this->assertEquals('/v1/checkout/begin', $event->getEndpoint());
    }

    public function testGetAction(): void
    {
        $event = new BeginCheckoutEvent('USD', 99.99);

        $this->assertEquals('begin_checkout', $event->getAction());
    }

    public function testToArray(): void
    {
        $event = (new BeginCheckoutEvent('USD', 129.99))
            ->setClientId('visitor-123')
            ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(99.99)->setQuantity(1))
            ->addItem((new Product('SKU-002'))->setItemName('Product 2')->setPrice(30.00)->setQuantity(1))
            ->setCoupon('SAVE10');

        $array = $event->toArray();

        $this->assertEquals('USD', $array['currency']);
        $this->assertEquals(129.99, $array['value']);
        $this->assertEquals('SAVE10', $array['coupon']);
        $this->assertCount(2, $array['items']);
        $this->assertEquals('SKU-001', $array['items'][0]['item_id']);
        $this->assertEquals('SKU-002', $array['items'][1]['item_id']);
    }

    public function testCurrencyUppercased(): void
    {
        $event = (new BeginCheckoutEvent('usd', 99.99))
            ->setClientId('visitor-123')
            ->addItem(new Product('SKU-001'));

        $array = $event->toArray();

        $this->assertEquals('USD', $array['currency']);
    }

    public function testValidateSuccess(): void
    {
        $event = (new BeginCheckoutEvent('USD', 99.99))
            ->setClientId('visitor-123')
            ->addItem(new Product('SKU-001'));

        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateMissingUserIdentifier(): void
    {
        $event = (new BeginCheckoutEvent('USD', 99.99))
            ->addItem(new Product('SKU-001'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id, user_id, or session_id is required');

        $event->validate();
    }

    public function testValidateMissingCurrency(): void
    {
        $event = (new BeginCheckoutEvent('', 99.99))
            ->setClientId('visitor-123')
            ->addItem(new Product('SKU-001'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('currency');

        $event->validate();
    }

    public function testValidateZeroValue(): void
    {
        $event = (new BeginCheckoutEvent('USD', 0))
            ->setClientId('visitor-123')
            ->addItem(new Product('SKU-001'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('positive');

        $event->validate();
    }

    public function testValidateNegativeValue(): void
    {
        $event = (new BeginCheckoutEvent('USD', -10))
            ->setClientId('visitor-123')
            ->addItem(new Product('SKU-001'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('positive');

        $event->validate();
    }

    public function testValidateEmptyItems(): void
    {
        $event = (new BeginCheckoutEvent('USD', 99.99))
            ->setClientId('visitor-123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Items array cannot be empty');

        $event->validate();
    }

    public function testSetItemsFromArray(): void
    {
        $event = (new BeginCheckoutEvent('USD', 99.99))
            ->setClientId('visitor-123')
            ->setItems([
                ['item_id' => 'SKU-001', 'item_name' => 'Product 1', 'price' => 49.99],
                ['item_id' => 'SKU-002', 'item_name' => 'Product 2', 'price' => 50.00],
            ]);

        $array = $event->toArray();

        $this->assertCount(2, $array['items']);
        $this->assertEquals('SKU-001', $array['items'][0]['item_id']);
        $this->assertEquals('SKU-002', $array['items'][1]['item_id']);
    }
}
