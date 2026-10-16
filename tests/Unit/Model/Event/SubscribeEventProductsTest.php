<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\SubscribeEvent;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Products, value and event salt on SubscribeEvent (lead contract, docs/claude/leads.md).
 *
 * @covers \AxiTrace\Model\Event\SubscribeEvent
 * @covers \AxiTrace\Model\Event\LeadProductsTrait
 * @covers \AxiTrace\Model\ProductNormalizer
 */
final class SubscribeEventProductsTest extends TestCase
{
    use LeadWireFixtures;

    public function testExistingCallIsByteIdentical(): void
    {
        $event = (new SubscribeEvent('user@example.com'))
            ->setClientId('c-1')
            ->setUserId('u-1')
            ->setSessionId('s-1')
            ->setSubscriptionType('newsletter')
            ->setParams(['products' => 'x', 'value' => 5])
            ->setFirstName('Ada');

        // Captured from the SDK before lead products existed.
        self::assertSame(
            '{"client_id":"c-1","user_id":"u-1","session_id":"s-1","email":"user@example.com",'
            . '"subscription_type":"newsletter","params":{"products":"x","value":5,"first_name":"Ada"}}',
            json_encode($event->toArray())
        );
    }

    public function testWithoutProductsSendsNoProductsKey(): void
    {
        $payload = $this->newEvent()->toArray();

        self::assertArrayNotHasKey('products', $payload);
        self::assertArrayNotHasKey('value', $payload);
        self::assertArrayNotHasKey('eventSalt', $payload);
    }

    public function testValueAndEventSaltAreRootFields(): void
    {
        $payload = $this->newEvent()->setValue(49.0, 'eur')->setEventSalt('salt-9')->toArray();

        self::assertSame(49.0, $payload['value']);
        self::assertSame('EUR', $payload['currency']);
        self::assertSame('salt-9', $payload['eventSalt']);
    }

    public function testProductInstanceAndArrayNormalizeAlike(): void
    {
        $payload = $this->newEvent()
            ->setValue(98.0, 'EUR')
            ->addProduct((new Product('PLAN-PRO-M'))->setItemName('Pro plan, monthly')->setPrice(49.0)->setUnitCost(5.0))
            ->addProduct(['sku' => 'PLAN-PRO-Y', 'name' => 'Pro plan, yearly', 'quantity' => '1', 'finalUnitPrice' => 49, 'unitCost' => 5])
            ->toArray();

        self::assertSame([
            ['sku' => 'PLAN-PRO-M', 'name' => 'Pro plan, monthly', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 49.0, 'currency' => 'EUR']],
            ['sku' => 'PLAN-PRO-Y', 'name' => 'Pro plan, yearly', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 49.0, 'currency' => 'EUR']],
        ], $payload['products']);
    }

    public function testInvalidAndMixedCurrencyLinesAreDropped(): void
    {
        $event = $this->newEvent()
            ->setValue(49.0, 'EUR')
            ->setProducts([
                ['name' => 'No sku', 'quantity' => 1, 'finalUnitPrice' => 1],
                ['sku' => 'USD', 'name' => 'Dollar', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'USD']],
                ['sku' => 'OK', 'name' => 'Kept', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'EUR']],
            ]);

        $event->validate();

        self::assertSame(['OK'], array_column($event->toArray()['products'], 'sku'));
    }

    public function testATypeErrorStillThrows(): void
    {
        $this->expectException(ValidationException::class);

        $this->newEvent()->addProduct(null);
    }

    public function testMatchesTheSubscribeWireFixture(): void
    {
        $request = self::leadFixture('subscribe')['request'];

        self::assertSamePayload($request, $this->fixtureEvent($request)->toArray());
    }

    public function testMatchesTheValueCurrencyWireFixture(): void
    {
        $fixture = self::leadFixture('subscribe-value-currency');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)
            ->setProducts($request['products'])
            ->setValue($request['value'], $request['currency'])
            ->toArray();

        self::assertSamePayload($request, $payload);
        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
    }

    private function newEvent(): SubscribeEvent
    {
        return (new SubscribeEvent('user@example.com'))->setClientId('visitor-1');
    }

    /**
     * @param array<string, mixed> $request
     */
    private function fixtureEvent(array $request): SubscribeEvent
    {
        return (new SubscribeEvent($request['email']))
            ->setClientId($request['client_id'])
            ->setUserId($request['user_id'])
            ->setSessionId($request['session_id'])
            ->setSubscriptionType($request['subscription_type'])
            ->setEventSalt($request['eventSalt']);
    }
}
