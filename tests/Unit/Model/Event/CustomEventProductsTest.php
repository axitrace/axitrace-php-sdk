<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\CustomEvent;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Products on CustomEvent (lead contract, docs/claude/leads.md).
 *
 * @covers \AxiTrace\Model\Event\CustomEvent
 * @covers \AxiTrace\Model\Event\LeadProductsTrait
 * @covers \AxiTrace\Model\ProductNormalizer
 */
final class CustomEventProductsTest extends TestCase
{
    use LeadWireFixtures;

    public function testExistingCallIsByteIdentical(): void
    {
        $event = (new CustomEvent('quote_requested', ['plan' => 'pro', 'seats' => 3], 120.5, 'EUR', 'T-1', 'evt-1'))
            ->setClientId('c-1')
            ->setSessionId('s-1');

        // Captured from the SDK before lead products existed.
        self::assertSame(
            '{"event_name":"quote_requested","event_id":"evt-1","properties":{"plan":"pro","seats":3},'
            . '"value":120.5,"currency":"EUR","transaction_id":"T-1","client_id":"c-1","session_id":"s-1"}',
            json_encode($event->toArray())
        );
    }

    public function testWithoutProductsSendsNoProductsKey(): void
    {
        self::assertArrayNotHasKey('products', (new CustomEvent('quote_requested'))->toArray());
    }

    public function testProductsStayOutOfTheScalarProperties(): void
    {
        $event = (new CustomEvent('quote_requested', ['plan' => 'pro'], 49.0, 'EUR', null, 'evt-2'))
            ->addProduct((new Product('PLAN-PRO-M'))->setItemName('Pro plan, monthly')->setPrice(49.0)->setUnitCost(9.0))
            ->addProduct(['sku' => 'USD', 'name' => 'Dollar', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'USD']]);

        $event->validate();
        $payload = $event->toArray();

        self::assertSame(['plan' => 'pro'], $payload['properties']);
        self::assertSame([
            ['sku' => 'PLAN-PRO-M', 'name' => 'Pro plan, monthly', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 49.0, 'currency' => 'EUR']],
        ], $payload['products']);
    }

    public function testATypeErrorStillThrows(): void
    {
        $this->expectException(ValidationException::class);

        (new CustomEvent('quote_requested'))->addProduct(12.5);
    }

    public function testMatchesTheSelectedCustomWireFixture(): void
    {
        $fixture = self::leadFixture('custom-selected');
        $request = $fixture['request'];

        $payload = (new CustomEvent(
            $request['event_name'],
            $request['properties'],
            $request['value'],
            $request['currency'],
            null,
            $request['event_id']
        ))
            ->setClientId($request['client_id'])
            ->setSessionId($request['session_id'])
            ->setProducts($request['products'])
            ->toArray();

        // The SDK sends the visitor as client_id; user_id comes from other server integrations.
        unset($request['user_id']);
        self::assertSamePayload($request, $payload);
        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
    }
}
