<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Money;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Profit tracking fields of /v1/transaction: tax, shipping, taxesIncluded, costs and the
 * per-product unitCost and externalId.
 */
class TransactionEventCostFieldsTest extends TestCase
{
    private function createEvent(string $currency = 'EUR'): TransactionEvent
    {
        $event = TransactionEvent::create('ORDER-1', 120.0, 100.0, $currency, 'CARD');
        $event->setClientCustomId('visitor-1');
        $event->setSessionId('session-1');
        $event->setEventSalt('ORDER-1');
        $event->setDiscountAmount(new Money(5.0, $currency));
        $event->setMetadata(['channel' => 'web']);
        $event->setFbp('fb.1.1700000000000.123');
        $event->setUrl('https://shop.example/thank-you');
        $event->setRecordedAt(new \DateTimeImmutable('2026-10-01T10:00:00Z'));
        $event->addParam('utm_source', 'google');

        return $event;
    }

    public function testTransactionWithoutNewSettersSerialisesExactlyAsBefore(): void
    {
        $event = $this->createEvent();
        $event->addProduct(['sku' => 'SKU-1', 'name' => 'Chair', 'finalUnitPrice' => 60.0, 'quantity' => 2]);
        $event->addProduct((new Product('ITEM-2'))->setSku('SKU-2')->setItemName('Desk')->setPrice(10.0));

        $expected = [
            'client' => ['customId' => 'visitor-1'],
            'orderId' => 'ORDER-1',
            'source' => 'WEB_DESKTOP',
            'revenue' => ['amount' => 120.0, 'currency' => 'EUR'],
            'value' => ['amount' => 100.0, 'currency' => 'EUR'],
            'paymentInfo' => ['method' => 'CARD'],
            'products' => [
                [
                    'sku' => 'SKU-1',
                    'name' => 'Chair',
                    'finalUnitPrice' => ['amount' => 60.0, 'currency' => 'EUR'],
                    'quantity' => 2,
                ],
                [
                    'sku' => 'SKU-2',
                    'name' => 'Desk',
                    'quantity' => 1,
                    'finalUnitPrice' => ['amount' => 10.0, 'currency' => 'EUR'],
                ],
            ],
            'sessionId' => 'session-1',
            'fbp' => 'fb.1.1700000000000.123',
            'url' => 'https://shop.example/thank-you',
            'discountAmount' => ['amount' => 5.0, 'currency' => 'EUR'],
            'metadata' => ['channel' => 'web'],
            'eventSalt' => 'ORDER-1',
            'recordedAt' => '2026-10-01T10:00:00.000Z',
            'params' => ['utm_source' => 'google'],
        ];

        $this->assertSame(json_encode($expected), json_encode($event->toArray()));
    }

    public function testCostFieldsSerialiseWithTheContractNames(): void
    {
        $event = $this->createEvent();
        $event->setTax(19.0)
            ->setShipping(12.3)
            ->setTaxesIncluded(true)
            ->setCosts(new Money(8.5, 'EUR'), 2.1, ['amount' => 1.5, 'currency' => 'eur']);
        $event->addProduct([
            'sku' => 'SKU-1',
            'name' => 'Chair',
            'finalUnitPrice' => 60.0,
            'quantity' => 2,
            'unitCost' => 31.25,
            'externalId' => 'woocommerce:1234',
        ]);

        $data = $event->toArray();

        $this->assertSame(19.0, $data['tax']);
        $this->assertSame(12.3, $data['shipping']);
        $this->assertTrue($data['taxesIncluded']);
        $this->assertSame([
            'shipping' => ['amount' => 8.5, 'currency' => 'EUR'],
            'paymentFee' => ['amount' => 2.1, 'currency' => 'EUR'],
            'handling' => ['amount' => 1.5, 'currency' => 'EUR'],
        ], $data['costs']);
        $this->assertSame(['amount' => 31.25, 'currency' => 'EUR'], $data['products'][0]['unitCost']);
        $this->assertSame('woocommerce:1234', $data['products'][0]['externalId']);
    }

    public function testTaxesIncludedFalseIsSentNotDropped(): void
    {
        $event = $this->createEvent();
        $event->setTaxesIncluded(false);

        $this->assertFalse($event->toArray()['taxesIncluded']);
    }

    public function testOnlyTheCostsThatAreSetAreSent(): void
    {
        $event = $this->createEvent();
        $event->setCosts(null, 2.0);

        $this->assertSame(['paymentFee' => ['amount' => 2.0, 'currency' => 'EUR']], $event->toArray()['costs']);
    }

    public function testSetCostsWithNothingSetSendsNoCostsObject(): void
    {
        $event = $this->createEvent();
        $event->setCosts(5.0);
        $event->setCosts();

        $this->assertArrayNotHasKey('costs', $event->toArray());
    }

    public function testProductModelCarriesUnitCostAndExternalId(): void
    {
        $event = $this->createEvent('PLN');
        $event->addProduct(
            (new Product('ITEM-1'))
                ->setSku('SKU-1')
                ->setItemName('Chair')
                ->setPrice(60.0)
                ->setQuantity(1)
                ->setUnitCost(25.0)
                ->setExternalId('shopware:abc')
        );

        $product = $event->toArray()['products'][0];

        $this->assertSame(['amount' => 25.0, 'currency' => 'PLN'], $product['unitCost']);
        $this->assertSame('shopware:abc', $product['externalId']);
    }

    public function testIntegerExternalIdIsSentAsString(): void
    {
        $event = $this->createEvent();
        $event->addProduct(['sku' => 'SKU-1', 'name' => 'Chair', 'externalId' => 1234]);

        $this->assertSame('1234', $event->toArray()['products'][0]['externalId']);
    }

    public function testProductModelToArrayNeverCarriesCostData(): void
    {
        $product = (new Product('ITEM-1'))->setUnitCost(25.0)->setExternalId('magento:9');

        $this->assertSame(['item_id' => 'ITEM-1'], $product->toArray());
    }

    public function testProductFromArrayReadsCostFields(): void
    {
        $product = Product::fromArray(['sku' => 'SKU-1', 'unit_cost' => '12.5', 'externalId' => 'woocommerce:7']);

        $this->assertSame(12.5, $product->getUnitCost());
        $this->assertSame('woocommerce:7', $product->getExternalId());
    }

    /**
     * @return array<string, array{0: callable(TransactionEvent): void, 1: string}>
     */
    public function invalidCostProvider(): array
    {
        return [
            'negative tax' => [static function (TransactionEvent $e): void {
                $e->setTax(-1.0);
            }, 'tax'],
            'negative shipping' => [static function (TransactionEvent $e): void {
                $e->setShipping(-0.01);
            }, 'shipping'],
            'negative cost' => [static function (TransactionEvent $e): void {
                $e->setCosts(-3.0);
            }, 'costs.shipping'],
            'cost in another currency' => [static function (TransactionEvent $e): void {
                $e->setCosts(null, new Money(1.0, 'USD'));
            }, 'costs.paymentFee'],
            'cost array in another currency' => [static function (TransactionEvent $e): void {
                $e->setCosts(null, null, ['amount' => 1.0, 'currency' => 'USD']);
            }, 'costs.handling'],
            'non-numeric cost' => [static function (TransactionEvent $e): void {
                $e->setCosts('cheap');
            }, 'costs.shipping'],
            'negative unit cost' => [static function (TransactionEvent $e): void {
                $e->addProduct(['sku' => 'S', 'name' => 'N', 'unitCost' => -5]);
            }, 'products[].unitCost'],
            'unit cost in another currency' => [static function (TransactionEvent $e): void {
                $e->addProduct(['sku' => 'S', 'name' => 'N', 'unitCost' => ['amount' => 5, 'currency' => 'USD']]);
            }, 'products[].unitCost'],
            'empty external id' => [static function (TransactionEvent $e): void {
                $e->addProduct(['sku' => 'S', 'name' => 'N', 'externalId' => ' ']);
            }, 'products[].externalId'],
        ];
    }

    /**
     * @dataProvider invalidCostProvider
     * @param callable(TransactionEvent): void $apply
     */
    public function testInvalidCostDataFailsBeforeSending(callable $apply, string $field): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage($field);

        $apply($this->createEvent());
    }
}
