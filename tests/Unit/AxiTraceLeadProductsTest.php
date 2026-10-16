<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit;

use AxiTrace\AxiTrace;
use AxiTrace\Config;
use AxiTrace\Exception\ValidationException;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

/**
 * formSubmit/subscribe/startTrial read products, value, currency and eventSalt from $params.
 *
 * @covers \AxiTrace\AxiTrace
 */
final class AxiTraceLeadProductsTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $requestHistory = [];

    public function testExistingHelperCallsAreByteIdentical(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->formSubmit('contact-form', ['email' => 'user@example.com', 'message' => 'Hi'], ['utm_source' => 'google', 'extra' => 'x']);
        $axiTrace->subscribe('user@example.com', ['subscription_type' => 'newsletter', 'extra' => 'x']);
        $axiTrace->startTrial('Pro', ['trial_period_days' => 14, 'trial_value' => 49, 'trial_currency' => 'EUR', 'email' => 'a@example.com', 'extra' => 'x']);

        // Captured from the SDK before lead products existed.
        self::assertSame([
            '{"label":"contact-form","client":{"customId":"visitor-1","email":"user@example.com"},"params":{"email":"user@example.com","message":"Hi","utm_source":"google","extra":"x"},"sessionId":"sess-1"}',
            '{"client_id":"visitor-1","session_id":"sess-1","email":"user@example.com","subscription_type":"newsletter","params":{"extra":"x"}}',
            '{"client_id":"visitor-1","session_id":"sess-1","plan_name":"Pro","trial_period_days":14,"trial_value":49,"trial_currency":"EUR","email":"a@example.com","params":{"extra":"x"}}',
        ], $this->sentBodies());
    }

    public function testFormSubmitReadsLeadFieldsFromParams(): void
    {
        $this->createAxiTrace()->formSubmit('quote-request', ['email' => 'user@example.com'], [
            'eventSalt' => 'salt-1',
            'value' => 149,
            'currency' => 'eur',
            'products' => [
                ['sku' => 'CHAIR', 'name' => 'Chair', 'quantity' => 1, 'finalUnitPrice' => 149, 'unitCost' => 50],
                ['sku' => 'NO-NAME', 'quantity' => 1, 'finalUnitPrice' => 1],
            ],
            'extra' => 'x',
        ]);

        $body = json_decode($this->sentBodies()[0], true);
        self::assertSame('salt-1', $body['eventSalt']);
        self::assertSame(149, $body['value']);
        self::assertSame('EUR', $body['currency']);
        self::assertSame([
            ['sku' => 'CHAIR', 'name' => 'Chair', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 149, 'currency' => 'EUR']],
        ], $body['products']);
        self::assertSame(['email' => 'user@example.com', 'extra' => 'x'], $body['params']);
    }

    public function testSubscribeReadsLeadFieldsFromParams(): void
    {
        $this->createAxiTrace()->subscribe('user@example.com', [
            'eventSalt' => 'salt-2',
            'value' => 49.5,
            'currency' => 'EUR',
            'products' => [['sku' => 'PLAN', 'name' => 'Plan', 'quantity' => 1, 'finalUnitPrice' => 49.5]],
        ]);

        $body = json_decode($this->sentBodies()[0], true);
        self::assertSame('salt-2', $body['eventSalt']);
        self::assertSame(49.5, $body['value']);
        self::assertSame('EUR', $body['currency']);
        self::assertSame(['PLAN'], array_column($body['products'], 'sku'));
        self::assertArrayNotHasKey('params', $body);
    }

    public function testStartTrialReadsProductsAndEventSaltFromParams(): void
    {
        $this->createAxiTrace()->startTrial('Pro', [
            'trial_value' => 49,
            'trial_currency' => 'EUR',
            'eventSalt' => 'salt-3',
            'products' => [['sku' => 'PLAN', 'name' => 'Plan', 'quantity' => 1, 'finalUnitPrice' => 49]],
        ]);

        $body = json_decode($this->sentBodies()[0], true);
        self::assertSame('salt-3', $body['eventSalt']);
        self::assertSame([['sku' => 'PLAN', 'name' => 'Plan', 'quantity' => 1, 'finalUnitPrice' => ['amount' => 49, 'currency' => 'EUR']]], $body['products']);
        self::assertArrayNotHasKey('params', $body);
    }

    public function testProductsThatAreNotAListAreRejectedBeforeSending(): void
    {
        $axiTrace = $this->createAxiTrace();

        try {
            $axiTrace->subscribe('user@example.com', ['products' => 'SKU-1']);
            self::fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            self::assertStringContainsString('Invalid products', $e->getMessage());
        }

        self::assertSame([], $this->requestHistory);
    }

    private function createAxiTrace(): AxiTrace
    {
        $this->requestHistory = [];
        $responses = [];
        for ($i = 0; $i < 3; $i++) {
            $responses[] = new Response(202, ['Content-Type' => 'application/json'], (string) json_encode(['success' => true]));
        }
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->requestHistory));

        $axiTrace = new AxiTrace(
            new Config('sk_test_dummy_key', ['base_url' => 'https://stat.axitrace.com']),
            new Client(['handler' => $stack])
        );
        $axiTrace->setClientId('visitor-1');
        $axiTrace->setSessionId('sess-1');

        return $axiTrace;
    }

    /**
     * @return array<int, string>
     */
    private function sentBodies(): array
    {
        return array_map(static function (array $entry): string {
            return (string) $entry['request']->getBody();
        }, $this->requestHistory);
    }
}
