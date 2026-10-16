<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\ClientIdentity;
use AxiTrace\Model\Event\FormSubmitEvent;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Products and value on FormSubmitEvent (lead contract, docs/claude/leads.md).
 *
 * @covers \AxiTrace\Model\Event\FormSubmitEvent
 * @covers \AxiTrace\Model\Event\LeadProductsTrait
 * @covers \AxiTrace\Model\ProductNormalizer
 */
final class FormSubmitEventProductsTest extends TestCase
{
    use LeadWireFixtures;

    public function testExistingCallIsByteIdentical(): void
    {
        $event = (new FormSubmitEvent('contact-form'))
            ->setClientCustomId('visitor-123')
            ->setClientEmail('user@example.com')
            ->setClientPhone('+48500100200')
            ->setSessionId('session-456')
            ->setEventSalt('salt-1')
            ->setFormParams(['email' => 'user@example.com', 'message' => 'Hello', 'value' => 10])
            ->addParam('utm_source', 'google');

        // Captured from the SDK before lead products existed.
        self::assertSame(
            '{"label":"contact-form","client":{"customId":"visitor-123","email":"user@example.com",'
            . '"phone":"+48500100200"},"params":{"email":"user@example.com","message":"Hello","value":10,'
            . '"utm_source":"google"},"sessionId":"session-456","eventSalt":"salt-1"}',
            json_encode($event->toArray())
        );
    }

    public function testWithoutProductsSendsNoProductsKey(): void
    {
        $payload = $this->newEvent()->toArray();

        self::assertArrayNotHasKey('products', $payload);
        self::assertArrayNotHasKey('value', $payload);
        self::assertArrayNotHasKey('currency', $payload);
    }

    public function testArrayProductIsSentInTheTransactionShape(): void
    {
        $payload = $this->newEvent()
            ->setValue(298.0, 'eur')
            ->addProduct([
                'sku' => 'CHAIR-OAK-01',
                'name' => 'Oak dining chair',
                'quantity' => 2,
                'finalUnitPrice' => ['amount' => 149.0, 'currency' => 'eur'],
                'categories' => ['Furniture'],
            ])
            ->toArray();

        self::assertSame([[
            'sku' => 'CHAIR-OAK-01',
            'name' => 'Oak dining chair',
            'quantity' => 2,
            'finalUnitPrice' => ['amount' => 149.0, 'currency' => 'EUR'],
            'categories' => ['Furniture'],
        ]], $payload['products']);
        self::assertSame(298.0, $payload['value']);
        self::assertSame('EUR', $payload['currency']);
    }

    public function testProductInstanceFallsBackToItemIdAndQuantityOne(): void
    {
        $payload = $this->newEvent()
            ->setValue(50.0, 'EUR')
            ->addProduct((new Product('ITEM-9'))->setItemName('Table')->setPrice(50.0)->setExternalId('woo:9'))
            ->toArray();

        self::assertSame([[
            'sku' => 'ITEM-9',
            'name' => 'Table',
            'quantity' => 1,
            'finalUnitPrice' => ['amount' => 50.0, 'currency' => 'EUR'],
            'externalId' => 'woo:9',
        ]], $payload['products']);
    }

    public function testUnitCostIsNeverSerialized(): void
    {
        $payload = $this->newEvent()
            ->setValue(10.0, 'EUR')
            ->setProducts([
                ['sku' => 'A', 'name' => 'A', 'quantity' => 1, 'finalUnitPrice' => 5, 'unitCost' => 2],
                (new Product('B'))->setItemName('B')->setPrice(5.0)->setUnitCost(2.0),
            ])
            ->toArray();

        self::assertCount(2, $payload['products']);
        foreach ($payload['products'] as $line) {
            self::assertArrayNotHasKey('unitCost', $line);
        }
    }

    public function testScalarPriceIsWrappedAndNumericStringQuantityIsCast(): void
    {
        $payload = $this->newEvent()
            ->setValue(50.0, 'EUR')
            ->addProduct(['sku' => 'S', 'name' => 'Scalar', 'quantity' => '2', 'finalUnitPrice' => '25'])
            ->toArray();

        self::assertSame([[
            'sku' => 'S',
            'name' => 'Scalar',
            'quantity' => 2,
            'finalUnitPrice' => ['amount' => 25.0, 'currency' => 'EUR'],
        ]], $payload['products']);
    }

    public function testInvalidLinesAreDroppedAndTheEventIsStillBuilt(): void
    {
        $event = $this->newEvent()
            ->setValue(100.0, 'EUR')
            ->setProducts([
                ['name' => 'No sku', 'quantity' => 1, 'finalUnitPrice' => 1],
                ['sku' => 'NO-NAME', 'quantity' => 1, 'finalUnitPrice' => 1],
                ['sku' => 'NO-PRICE', 'name' => 'No price', 'quantity' => 1],
                ['sku' => 'BAD-PRICE', 'name' => 'Bad price', 'quantity' => 1, 'finalUnitPrice' => ['currency' => 'EUR']],
                ['sku' => 'TEXT-PRICE', 'name' => 'Text price', 'quantity' => 1, 'finalUnitPrice' => 'free'],
                ['sku' => 'ZERO', 'name' => 'Zero quantity', 'quantity' => 0, 'finalUnitPrice' => 1],
                ['sku' => 'NO-QTY', 'name' => 'No quantity', 'finalUnitPrice' => 1],
                ['sku' => 'FLOAT-QTY', 'name' => 'Float quantity', 'quantity' => 1.5, 'finalUnitPrice' => 1],
                ['sku' => ' ', 'name' => 'Blank sku', 'quantity' => 1, 'finalUnitPrice' => 1],
                ['sku' => 'USD', 'name' => 'Other currency', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'USD']],
                ['sku' => 'OK', 'name' => 'Kept', 'quantity' => 1, 'finalUnitPrice' => 3, 'externalId' => ''],
            ]);

        $event->validate();
        $payload = $event->toArray();

        self::assertSame([[
            'sku' => 'OK',
            'name' => 'Kept',
            'quantity' => 1,
            'finalUnitPrice' => ['amount' => 3.0, 'currency' => 'EUR'],
        ]], $payload['products']);
    }

    public function testOnlyInvalidLinesSendNoProductsKey(): void
    {
        $payload = $this->newEvent()
            ->addProduct(['sku' => 'NO-PRICE', 'name' => 'No price', 'quantity' => 1])
            ->toArray();

        self::assertArrayNotHasKey('products', $payload);
    }

    public function testWithoutValueTheFirstValidLineSetsTheCurrency(): void
    {
        $payload = $this->newEvent()
            ->setProducts([
                ['sku' => 'BROKEN', 'name' => 'Dropped first', 'quantity' => 0, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'PLN']],
                ['sku' => 'EUR-1', 'name' => 'Euro', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 10, 'currency' => 'EUR']],
                ['sku' => 'USD-1', 'name' => 'Dollar', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 10, 'currency' => 'USD']],
                ['sku' => 'SCALAR', 'name' => 'Scalar', 'quantity' => 1, 'finalUnitPrice' => 4],
            ])
            ->toArray();

        self::assertSame(['EUR-1', 'SCALAR'], array_column($payload['products'], 'sku'));
        self::assertSame(['EUR', 'EUR'], array_column(array_column($payload['products'], 'finalUnitPrice'), 'currency'));
        self::assertArrayNotHasKey('value', $payload);
    }

    public function testScalarPricesWithoutAnyKnownCurrencyAreDropped(): void
    {
        $payload = $this->newEvent()
            ->addProduct(['sku' => 'SCALAR', 'name' => 'Scalar', 'quantity' => 1, 'finalUnitPrice' => 4])
            ->toArray();

        self::assertArrayNotHasKey('products', $payload);
    }

    public function testValueSetAfterProductsStillDecidesTheCurrency(): void
    {
        $payload = $this->newEvent()
            ->addProduct(['sku' => 'EUR-1', 'name' => 'Euro', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 10, 'currency' => 'EUR']])
            ->addProduct(['sku' => 'USD-1', 'name' => 'Dollar', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 10, 'currency' => 'USD']])
            ->setValue(10.0, 'usd')
            ->toArray();

        self::assertSame(['USD-1'], array_column($payload['products'], 'sku'));
    }

    public function testSetProductsReplacesEarlierLines(): void
    {
        $payload = $this->newEvent()
            ->setValue(1.0, 'EUR')
            ->addProduct(['sku' => 'OLD', 'name' => 'Old', 'quantity' => 1, 'finalUnitPrice' => 1])
            ->setProducts([['sku' => 'NEW', 'name' => 'New', 'quantity' => 1, 'finalUnitPrice' => 1]])
            ->toArray();

        self::assertSame(['NEW'], array_column($payload['products'], 'sku'));
    }

    public function testATypeErrorStillThrows(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Invalid form.submit product: expected an array or an AxiTrace\Model\Product instance, got string.');

        $this->newEvent()->addProduct('SKU-1');
    }

    public function testATypeErrorInSetProductsThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->newEvent()->setProducts([['sku' => 'A'], 42]);
    }

    public function testValueWithoutProductsMatchesTheWireFixture(): void
    {
        $fixture = self::leadFixture('form-value-without-products');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)
            ->setValue($request['value'], $request['currency'])
            ->toArray();

        unset($request['time']);
        self::assertSamePayload($request, $payload);
    }

    public function testPricedProductsMatchTheWireFixture(): void
    {
        $fixture = self::leadFixture('form-priced-products');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)
            ->setValue($request['value'], $request['currency'])
            ->setProducts($request['products'])
            ->toArray();

        unset($request['time']);
        self::assertSamePayload($request, $payload);
        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
    }

    public function testMixedLinesMatchTheKeptProductsOfTheWireFixture(): void
    {
        $fixture = self::leadFixture('form-mixed-product-lines');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)
            ->setValue($request['value'], $request['currency'])
            ->setProducts($request['products'])
            ->toArray();

        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
        self::assertSame($fixture['expect']['value'], $payload['value']);
        self::assertSame($fixture['expect']['currency'], $payload['currency']);
    }

    public function testFirstLineCurrencyMatchesTheWireFixture(): void
    {
        $fixture = self::leadFixture('form-first-line-currency');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)->setProducts($request['products'])->toArray();

        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
        self::assertArrayNotHasKey('value', $payload);
    }

    public function testInquiryWithoutProductsMatchesTheWireFixture(): void
    {
        $fixture = self::leadFixture('form-inquiry');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)->toArray();

        unset($request['time']);
        self::assertSamePayload($request, $payload);
    }

    private function newEvent(): FormSubmitEvent
    {
        return (new FormSubmitEvent('quote-request'))
            ->setClientCustomId('visitor-1')
            ->setFormParams(['email' => 'user@example.com']);
    }

    /**
     * Build the event the PHP SDK sends for a server fixture request (the SDK has no
     * occurrence-time field, so "time" is left out of the comparison).
     *
     * @param array<string, mixed> $request
     */
    private function fixtureEvent(array $request): FormSubmitEvent
    {
        return (new FormSubmitEvent($request['label']))
            ->setClient(ClientIdentity::fromArray($request['client']))
            ->setSessionId($request['sessionId'])
            ->setEventSalt($request['eventSalt'])
            ->setFormParams($request['params']);
    }
}
