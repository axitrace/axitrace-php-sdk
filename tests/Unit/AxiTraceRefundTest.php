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

class AxiTraceRefundTest extends TestCase
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $requestHistory = [];

    private function createAxiTrace(): AxiTrace
    {
        $this->requestHistory = [];
        $mock = new MockHandler([
            new Response(202, ['Content-Type' => 'application/json'], json_encode(['success' => true])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push(Middleware::history($this->requestHistory));

        $config = new Config('sk_test_dummy_key', ['base_url' => 'https://stat.axitrace.com']);

        return new AxiTrace($config, new Client(['handler' => $stack]));
    }

    public function testRefundPostsToTheRefundEndpointWithTheSecretKey(): void
    {
        $axiTrace = $this->createAxiTrace();
        $axiTrace->setClientId('visitor-1');
        $axiTrace->withContext(['consent' => 'granted']);

        $axiTrace->refund('ORDER-1', 'RF-1', 49.99, 'EUR', [
            ['sku' => 'SKU-1', 'externalId' => 'woocommerce:1234', 'quantity' => 1, 'amount' => 49.99],
        ], [
            'refunded_at' => '2026-10-02T14:30:00+02:00',
            'is_cancellation' => true,
        ]);

        $this->assertCount(1, $this->requestHistory);
        $request = $this->requestHistory[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/v1/refund', $request->getUri()->getPath());
        $this->assertSame('Basic ' . base64_encode('sk_test_dummy_key:'), $request->getHeaderLine('Authorization'));

        $this->assertSame([
            'orderId' => 'ORDER-1',
            'refundId' => 'RF-1',
            'refundedAt' => '2026-10-02T12:30:00.000Z',
            'amount' => 49.99,
            'currency' => 'EUR',
            'isCancellation' => true,
            'lines' => [
                ['sku' => 'SKU-1', 'externalId' => 'woocommerce:1234', 'quantity' => 1, 'amount' => 49.99],
            ],
        ], json_decode((string) $request->getBody(), true));
    }

    public function testUnknownRefundParamIsRejectedBeforeSending(): void
    {
        $axiTrace = $this->createAxiTrace();

        try {
            $axiTrace->refund('ORDER-1', 'RF-1', 1.0, 'EUR', [], ['reason' => 'damaged']);
            $this->fail('Expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('reason', $e->getMessage());
        }

        $this->assertCount(0, $this->requestHistory);
    }

    public function testInvalidRefundedAtIsRejectedBeforeSending(): void
    {
        $axiTrace = $this->createAxiTrace();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('refunded_at');

        $axiTrace->refund('ORDER-1', 'RF-1', 1.0, 'EUR', [], ['refundedAt' => 'not a date']);
    }
}
