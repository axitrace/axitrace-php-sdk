<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Money;
use PHPUnit\Framework\TestCase;

class TransactionEventTest extends TestCase
{
    public function testGetEndpoint(): void
    {
        $event = $this->createValidEvent();

        $this->assertEquals('/v1/transaction', $event->getEndpoint());
    }

    public function testGetAction(): void
    {
        $event = $this->createValidEvent();

        $this->assertEquals('transaction', $event->getAction());
    }

    public function testCreateStatic(): void
    {
        $event = TransactionEvent::create(
            'ORDER-123',
            99.99,
            89.99,
            'USD',
            'CARD'
        );

        $event->setClientCustomId('visitor-123');
        $event->addProduct([
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'],
            'quantity' => 1,
        ]);

        $array = $event->toArray();

        $this->assertEquals('ORDER-123', $array['orderId']);
        $this->assertEquals(99.99, $array['revenue']['amount']);
        $this->assertEquals(89.99, $array['value']['amount']);
        $this->assertEquals('USD', $array['revenue']['currency']);
        $this->assertEquals('CARD', $array['paymentInfo']['method']);
        $this->assertEquals('WEB_DESKTOP', $array['source']);
    }

    public function testToArray(): void
    {
        $event = $this->createValidEvent();
        $array = $event->toArray();

        $this->assertArrayHasKey('client', $array);
        $this->assertArrayHasKey('orderId', $array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('revenue', $array);
        $this->assertArrayHasKey('value', $array);
        $this->assertArrayHasKey('paymentInfo', $array);
        $this->assertArrayHasKey('products', $array);
    }

    public function testValidSources(): void
    {
        $sources = [
            TransactionEvent::SOURCE_WEB_DESKTOP,
            TransactionEvent::SOURCE_WEB_MOBILE,
            TransactionEvent::SOURCE_MOBILE_APP,
            TransactionEvent::SOURCE_POS,
            TransactionEvent::SOURCE_MOBILE,
            TransactionEvent::SOURCE_DESKTOP,
        ];

        foreach ($sources as $source) {
            $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD', $source);
            $event->setClientCustomId('visitor-123');
            $event->addProduct(['sku' => 'SKU-001', 'name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

            $event->validate();
            $this->assertTrue(true, "Source $source should be valid");
        }
    }

    public function testInvalidSource(): void
    {
        $event = new TransactionEvent(
            'ORDER-123',
            'INVALID_SOURCE',
            new Money(99.99, 'USD'),
            new Money(89.99, 'USD'),
            'CARD'
        );
        $event->setClientCustomId('visitor-123');
        $event->addProduct(['sku' => 'SKU-001', 'name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid source');

        $event->validate();
    }

    public function testValidateSuccess(): void
    {
        $event = $this->createValidEvent();

        // Should not throw
        $event->validate();
        $this->assertTrue(true);
    }

    public function testValidateMissingClient(): void
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
        $event->addProduct(['sku' => 'SKU-001', 'name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('client_id, user_id, or session_id is required');

        $event->validate();
    }

    public function testValidateMissingOrderId(): void
    {
        $event = TransactionEvent::create('', 99.99, 89.99, 'USD', 'CARD');
        $event->setClientCustomId('visitor-123');
        $event->addProduct(['sku' => 'SKU-001', 'name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('orderId');

        $event->validate();
    }

    public function testValidateMissingPaymentMethod(): void
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', '');
        $event->setClientCustomId('visitor-123');
        $event->addProduct(['sku' => 'SKU-001', 'name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('paymentInfo.method');

        $event->validate();
    }

    public function testValidateEmptyProducts(): void
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
        $event->setClientCustomId('visitor-123');

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Items array cannot be empty');

        $event->validate();
    }

    public function testValidateProductMissingSku(): void
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
        $event->setClientCustomId('visitor-123');
        $event->addProduct(['name' => 'Test', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('sku');

        $event->validate();
    }

    public function testValidateProductMissingName(): void
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
        $event->setClientCustomId('visitor-123');
        $event->addProduct(['sku' => 'SKU-001', 'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'], 'quantity' => 1]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('name');

        $event->validate();
    }

    public function testWithDiscountAmount(): void
    {
        $event = $this->createValidEvent();
        $event->setDiscountAmount(new Money(10.00, 'USD'));

        $array = $event->toArray();

        $this->assertArrayHasKey('discountAmount', $array);
        $this->assertEquals(10.00, $array['discountAmount']['amount']);
        $this->assertEquals('USD', $array['discountAmount']['currency']);
    }

    public function testWithMetadata(): void
    {
        $event = $this->createValidEvent();
        $event->setMetadata(['campaign' => 'summer_sale', 'affiliate_id' => 'AFF123']);

        $array = $event->toArray();

        $this->assertArrayHasKey('metadata', $array);
        $this->assertEquals('summer_sale', $array['metadata']['campaign']);
        $this->assertEquals('AFF123', $array['metadata']['affiliate_id']);
    }

    public function testWithEventSalt(): void
    {
        $event = $this->createValidEvent();
        $event->setEventSalt('unique-event-id-123');

        $array = $event->toArray();

        $this->assertArrayHasKey('eventSalt', $array);
        $this->assertEquals('unique-event-id-123', $array['eventSalt']);
    }

    private function createValidEvent(): TransactionEvent
    {
        $event = TransactionEvent::create('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
        $event->setClientCustomId('visitor-123');
        $event->addProduct([
            'sku' => 'SKU-001',
            'name' => 'Test Product',
            'finalUnitPrice' => ['amount' => 89.99, 'currency' => 'USD'],
            'quantity' => 1,
        ]);

        return $event;
    }
}
