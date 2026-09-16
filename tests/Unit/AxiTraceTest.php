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
     * object - that is the only shape the ingestion API maps onto Meta CAPI fn/ln/ct/st/zp/
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
     * forwarded as a generic event param where the ingestion API ignores it - duplicated
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

    /**
     * The consent state must reach the API as params.consent on the transaction payload.
     * That single key is what the ingestion API keeps on the queued event and what the
     * event worker reads to decide whether a purchase may be forwarded to an ad
     * platform: a refusal that never leaves the SDK is a refusal nobody honours.
     */
    public function testWithContextConsentTravelsInTransactionParams(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->withContext(['consent' => 'denied'])->transaction('ORDER-123', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ]);

        $body = $this->lastRequestBody();
        $this->assertSame('denied', $body['params']['consent']);
    }

    public function testWithContextConsentTravelsInPageViewParams(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->withContext(['consent' => 'granted'])->pageView('https://example.com/product');

        $body = $this->lastRequestBody();
        $this->assertSame('granted', $body['params']['consent']);
    }

    public function testWithContextConsentAppliesToEveryLaterEvent(): void
    {
        $axiTrace = $this->createAxiTrace(2);
        $axiTrace->setClientId('visitor-123');
        $axiTrace->withContext(['consent' => 'unknown']);

        $axiTrace->pageView('https://example.com/first');
        $this->assertSame('unknown', $this->lastRequestBody()['params']['consent']);

        $axiTrace->pageView('https://example.com/second');
        $this->assertSame('unknown', $this->lastRequestBody()['params']['consent']);
    }

    public function testWithContextConsentOverridesAConflictingEventParam(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->withContext(['consent' => 'denied'])
            ->pageView('https://example.com/product', ['consent' => 'granted']);

        $this->assertSame('denied', $this->lastRequestBody()['params']['consent']);
    }

    /**
     * Without a consent key nothing changes: the payload carries no params.consent, which
     * is how every integration that does not report consent keeps behaving today.
     */
    public function testWithoutConsentContextNoConsentParamIsSent(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->withContext(['clientId' => 'visitor-123'])->pageView('https://example.com/product');

        $this->assertArrayNotHasKey('consent', $this->lastRequestBody()['params']);
    }

    /**
     * @dataProvider invalidConsentValuesProvider
     * @param mixed $consent
     */
    public function testWithContextRejectsAnInvalidConsentValue($consent): void
    {
        $axiTrace = $this->createAxiTrace(0);

        $this->expectException(ValidationException::class);

        $axiTrace->withContext(['consent' => $consent]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function invalidConsentValuesProvider(): array
    {
        return [
            'unsupported word' => ['accepted'],
            'wrong case' => ['GRANTED'],
            'empty string' => [''],
            'boolean' => [true],
            'integer' => [1],
            'array' => [['granted']],
        ];
    }

    /**
     * A rejected consent value must not leave the instance half-configured: the caller
     * catches the exception and retries, and a client ID applied by the failed call
     * would then silently belong to the wrong visitor.
     */
    public function testRejectedConsentLeavesTheContextUnapplied(): void
    {
        $axiTrace = $this->createAxiTrace(0);

        try {
            $axiTrace->withContext(['clientId' => 'ctx-client', 'consent' => 'accepted']);
            $this->fail('withContext() must reject an invalid consent state');
        } catch (ValidationException $e) {
            $this->assertNull($axiTrace->getVisitorId());
        }
    }

    public function testWithContextConsentNullIsTreatedAsNotReported(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-123');

        $axiTrace->withContext(['consent' => null])->pageView('https://example.com/product');

        $this->assertArrayNotHasKey('consent', $this->lastRequestBody()['params']);
    }
}
