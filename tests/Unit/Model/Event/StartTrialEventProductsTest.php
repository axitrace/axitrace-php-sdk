<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\StartTrialEvent;
use AxiTrace\Model\Product;
use PHPUnit\Framework\TestCase;

/**
 * Products and event salt on StartTrialEvent (lead contract, docs/claude/leads.md).
 *
 * @covers \AxiTrace\Model\Event\StartTrialEvent
 * @covers \AxiTrace\Model\Event\LeadProductsTrait
 * @covers \AxiTrace\Model\ProductNormalizer
 */
final class StartTrialEventProductsTest extends TestCase
{
    use LeadWireFixtures;

    public function testExistingCallIsByteIdentical(): void
    {
        $event = (new StartTrialEvent('Pro'))
            ->setClientId('c-1')
            ->setSessionId('s-1')
            ->setTrialPeriodDays(14)
            ->setTrialValue(49.0, 'eur')
            ->setPredictedLtv(500.5)
            ->setEmail('a@example.com')
            ->addParam('fbclid', 'fb-1');

        // Captured from the SDK before lead products existed.
        self::assertSame(
            '{"client_id":"c-1","session_id":"s-1","plan_name":"Pro","trial_period_days":14,"trial_value":49,'
            . '"trial_currency":"EUR","predicted_ltv":500.5,"email":"a@example.com","params":{"fbclid":"fb-1"}}',
            json_encode($event->toArray())
        );
    }

    public function testWithoutProductsSendsNoProductsKey(): void
    {
        $payload = (new StartTrialEvent('Pro'))->setClientId('c-1')->toArray();

        self::assertArrayNotHasKey('products', $payload);
        self::assertArrayNotHasKey('eventSalt', $payload);
    }

    public function testLinesFollowTheTrialCurrency(): void
    {
        $payload = (new StartTrialEvent('Pro'))
            ->setClientId('c-1')
            ->setTrialValue(49.0, 'EUR')
            ->addProduct((new Product('PLAN-PRO-M'))->setItemName('Pro plan, monthly')->setPrice(49.0))
            ->addProduct(['sku' => 'USD', 'name' => 'Dollar', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 1, 'currency' => 'USD']])
            ->addProduct(['sku' => 'NO-NAME', 'quantity' => 1, 'finalUnitPrice' => 1])
            ->toArray();

        self::assertSame([
            ['sku' => 'PLAN-PRO-M', 'name' => 'Pro plan, monthly', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 49.0, 'currency' => 'EUR']],
        ], $payload['products']);
    }

    public function testATypeErrorStillThrows(): void
    {
        $this->expectException(ValidationException::class);

        (new StartTrialEvent('Pro'))->setProducts([new \stdClass()]);
    }

    public function testMatchesTheStartTrialWireFixture(): void
    {
        $request = self::leadFixture('start-trial')['request'];

        self::assertSamePayload($request, $this->fixtureEvent($request)->toArray());
    }

    public function testMatchesTheStartTrialProductsWireFixture(): void
    {
        $fixture = self::leadFixture('start-trial-products');
        $request = $fixture['request'];

        $payload = $this->fixtureEvent($request)->setProducts($request['products'])->toArray();

        self::assertSamePayload($request, $payload);
        self::assertSamePayload($fixture['expect']['keptProducts'], $payload['products']);
    }

    /**
     * @param array<string, mixed> $request
     */
    private function fixtureEvent(array $request): StartTrialEvent
    {
        return (new StartTrialEvent($request['plan_name']))
            ->setClientId($request['client_id'])
            ->setUserId($request['user_id'])
            ->setSessionId($request['session_id'])
            ->setEmail($request['email'])
            ->setTrialPeriodDays($request['trial_period_days'])
            ->setTrialValue($request['trial_value'], $request['trial_currency'])
            ->setEventSalt($request['eventSalt']);
    }
}
