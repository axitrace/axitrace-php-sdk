<?php

declare(strict_types=1);

namespace AxiTrace\Tests\Unit;

use AxiTrace\AxiTrace;
use AxiTrace\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class AxiTraceTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $requestHistory = [];

    private function createAxiTrace(int $mockedResponses = 1): AxiTrace
    {
        $this->requestHistory = [];
        $history = Middleware::history($this->requestHistory);

        $responses = [];
        for ($i = 0; $i < $mockedResponses; $i++) {
            $responses[] = new Response(200, ['Content-Type' => 'application/json'], json_encode([
                'success' => true,
                'event_id' => 'evt_' . $i,
            ]));
        }

        $mock = new MockHandler($responses);
        $stack = HandlerStack::create($mock);
        $stack->push($history);
        $guzzle = new Client(['handler' => $stack]);

        $config = new Config('sk_test_dummy_key', ['base_url' => 'https://stat.axitrace.com']);

        return new AxiTrace($config, $guzzle);
    }

    private function lastRequestBody(): array
    {
        $lastIndex = count($this->requestHistory) - 1;
        $body = (string) $this->requestHistory[$lastIndex]['request']->getBody();

        return json_decode($body, true);
    }

    public function testTransactionEventSaltParamPropagatesToPayload(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ], [
            'event_salt' => 'ORDER-123',
        ]);

        $this->assertSame('ORDER-123', $this->lastRequestBody()['eventSalt']);
    }

    public function testTransactionAcceptsCamelCaseEventSaltKeyToo(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ], [
            'eventSalt' => 'ORDER-123-camel',
        ]);

        $this->assertSame('ORDER-123-camel', $this->lastRequestBody()['eventSalt']);
    }

    public function testTransactionWithoutEventSaltOmitsItFromPayload(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-456', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ]);

        $this->assertArrayNotHasKey('eventSalt', $this->lastRequestBody());
    }

    /**
     * The buyer's name and postal address must reach the API on the transaction's client
     * object — that is the only shape the ingestion API maps onto Meta CAPI fn/ln/ct/st/zp/
     * country and TikTok first_name/last_name/city/state/zip_code/country. Left inside the
     * generic params bag they are silently dropped, which is exactly how server-side
     * Purchase events ended up with 0% address coverage in production.
     */
    public function testTransactionPromotesBuyerAddressOntoClientObject(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ], [
            'email' => 'buyer@example.com',
            'phone' => '+41791234567',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'city' => 'Zurich',
            'state' => 'ZH',
            'zip' => '8001',
            'country' => 'CH',
        ]);

        $body = $this->lastRequestBody();

        $this->assertSame('buyer@example.com', $body['client']['email']);
        $this->assertSame('+41791234567', $body['client']['phone']);
        $this->assertSame('Ada', $body['client']['firstName']);
        $this->assertSame('Lovelace', $body['client']['lastName']);
        $this->assertSame('Zurich', $body['client']['city']);
        $this->assertSame('ZH', $body['client']['state']);
        $this->assertSame('8001', $body['client']['zip']);
        $this->assertSame('CH', $body['client']['country']);
    }

    public function testTransactionAcceptsCamelCaseAndPostalCodeAliases(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ], [
            'firstName' => 'Ada',
            'lastName' => 'Lovelace',
            'postalCode' => '8001',
            'province' => 'ZH',
        ]);

        $body = $this->lastRequestBody();

        $this->assertSame('Ada', $body['client']['firstName']);
        $this->assertSame('Lovelace', $body['client']['lastName']);
        $this->assertSame('8001', $body['client']['zip']);
        $this->assertSame('ZH', $body['client']['state']);
    }

    /**
     * A consumed match key must be removed from the params bag, otherwise it is also
     * forwarded as a generic event param where the ingestion API ignores it — duplicated
     * PII on the wire for no matching benefit.
     */
    public function testTransactionDoesNotAlsoForwardAddressAsGenericParams(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ], [
            'first_name' => 'Ada',
            'postalCode' => '8001',
            'utm_source' => 'facebook',
        ]);

        $params = $this->lastRequestBody()['params'] ?? [];

        $this->assertArrayNotHasKey('first_name', $params);
        $this->assertArrayNotHasKey('postalCode', $params);
        $this->assertSame('facebook', $params['utm_source']);
    }

    public function testTransactionOmitsAddressKeysWhenNotProvided(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ]);

        $client = $this->lastRequestBody()['client'];

        foreach (['firstName', 'lastName', 'city', 'state', 'zip', 'country'] as $key) {
            $this->assertArrayNotHasKey($key, $client);
        }
    }

    public function testWithContextAppliesAllFourSetters(): void
    {
        $axiTrace = $this->createAxiTrace();

        $result = $axiTrace->withContext([
            'clientId' => 'ctx-client',
            'sessionId' => 'ctx-session',
            'ip' => '203.0.113.9',
            'userAgent' => 'ctx-agent/1.0',
        ]);

        $this->assertSame($axiTrace, $result, 'withContext() must return $this for chaining');
        $this->assertSame('ctx-client', $axiTrace->getVisitorId());
        $this->assertSame('ctx-session', $axiTrace->getSessionId());
        $this->assertSame('203.0.113.9', $axiTrace->getClientIp());
        $this->assertSame('ctx-agent/1.0', $axiTrace->getClientUserAgent());
    }

    public function testWithContextAcceptsCustomIdAsClientIdAlias(): void
    {
        $axiTrace = $this->createAxiTrace();

        $axiTrace->withContext(['customId' => 'ctx-client-alias']);

        $this->assertSame('ctx-client-alias', $axiTrace->getVisitorId());
    }

    public function testWithContextIgnoresEmptyAndMissingKeys(): void
    {
        $axiTrace = $this->createAxiTrace();

        $axiTrace->withContext(['ip' => '']);

        $this->assertNull($axiTrace->getVisitorId());
        $this->assertNull($axiTrace->getSessionId());
    }
}
