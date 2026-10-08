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

/**
 * Click ids persisted by the JavaScript SDK in first-party cookies.
 *
 * The JavaScript SDK reads ttclid, gclid, gbraid, wbraid, rdt_cid and oppref from the
 * landing URL and keeps them in "_<name>" cookies with the value "v2|<firstSeenMs>|<id>".
 * A server-side conversion is usually sent from a later request (a form POST, a checkout
 * step) whose URL no longer carries the click id, so before the cookie fallback existed
 * the event left the server without it.
 */
class AxiTraceClickIdCookieTest extends TestCase
{
    private const DAY_MS = 86400000;

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $requestHistory = [];

    /**
     * @var array<string, mixed>
     */
    private array $savedGet = [];

    /**
     * @var array<string, mixed>
     */
    private array $savedCookie = [];

    protected function setUp(): void
    {
        $this->savedGet = $_GET;
        $this->savedCookie = $_COOKIE;
        $_GET = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->savedGet;
        $_COOKIE = $this->savedCookie;
    }

    /**
     * The SDK reads $_GET and $_COOKIE in its constructor, so the superglobals must be
     * populated before this is called.
     */
    private function createAxiTrace(): AxiTrace
    {
        $this->requestHistory = [];
        $history = Middleware::history($this->requestHistory);

        $mock = new MockHandler([
            new Response(200, ['Content-Type' => 'application/json'], (string) json_encode([
                'success' => true,
                'event_id' => 'evt_0',
            ])),
        ]);
        $stack = HandlerStack::create($mock);
        $stack->push($history);

        $axiTrace = new AxiTrace(
            new Config('sk_test_dummy_key', ['base_url' => 'https://stat.axitrace.com']),
            new Client(['handler' => $stack])
        );
        $axiTrace->setClientId('visitor-123');

        return $axiTrace;
    }

    /**
     * @return array<string, mixed>
     */
    private function lastRequestParams(): array
    {
        $lastIndex = count($this->requestHistory) - 1;
        $body = json_decode((string) $this->requestHistory[$lastIndex]['request']->getBody(), true);

        return is_array($body['params'] ?? null) ? $body['params'] : [];
    }

    private static function cookieValue(string $clickId, int $ageDays = 1): string
    {
        $firstSeenMs = (int) floor(microtime(true) * 1000) - $ageDays * self::DAY_MS;

        return 'v2|' . $firstSeenMs . '|' . $clickId;
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: int}>
     */
    public function persistedClickIdProvider(): array
    {
        return [
            'TikTok ttclid' => ['ttclid', '_ttclid', 90],
            'Google gclid' => ['gclid', '_gclid', 90],
            'Google gbraid' => ['gbraid', '_gbraid', 90],
            'Google wbraid' => ['wbraid', '_wbraid', 90],
            'Reddit rdt_cid' => ['rdt_cid', '_rdt_cid', 28],
            'OpenAI Ads oppref' => ['oppref', '_oppref', 28],
            'Microsoft msclkid' => ['msclkid', '_axi_msclkid', 90],
            'X twclid' => ['twclid', '_axi_twclid', 90],
            'Pinterest epik' => ['epik', '_axi_epik', 60],
            'LinkedIn li_fat_id' => ['li_fat_id', '_axi_li_fat_id', 30],
            'Snapchat sccid' => ['sccid', '_axi_sccid', 28],
        ];
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testClickIdFromUrlOnly(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_GET[$param] = 'url-click-id';

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('url-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * The defect: a request without the click id in its URL (POST /register/continue)
     * sent no click id even though the browser held it in a first-party cookie.
     *
     * @dataProvider persistedClickIdProvider
     */
    public function testClickIdFromCookieOnly(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('cookie-click-id');

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('cookie-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testUrlClickIdWinsOverCookie(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_GET[$param] = 'fresh-url-click-id';
        $_COOKIE[$cookieName] = self::cookieValue('older-cookie-click-id');

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('fresh-url-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testExplicitEventParamOverridesCookie(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('cookie-click-id');

        $this->createAxiTrace()->startTrial('free', [$param => 'explicit-click-id']);

        $this->assertSame('explicit-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testSetAttributionParamsOverridesCookie(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('cookie-click-id');

        $axiTrace = $this->createAxiTrace();
        $axiTrace->setAttributionParams([$param => 'framework-click-id']);
        $axiTrace->startTrial('free');

        $this->assertSame('framework-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testCookieClickIdReachesTransactionParams(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('cookie-click-id');

        $this->createAxiTrace()->transaction('ORDER-1', 10.0, 10.0, 'USD', 'CARD', [
            ['sku' => 'SKU-1', 'name' => 'Product 1', 'finalUnitPrice' => 10.0, 'quantity' => 1],
        ]);

        $this->assertSame('cookie-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testClickOlderThanItsMaxAgeIsIgnored(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('stale-click-id', $maxAgeDays + 1);

        $this->createAxiTrace()->startTrial('free');

        $this->assertArrayNotHasKey($param, $this->lastRequestParams());
    }

    /**
     * @dataProvider persistedClickIdProvider
     */
    public function testClickJustInsideItsMaxAgeIsKept(string $param, string $cookieName, int $maxAgeDays): void
    {
        $_COOKIE[$cookieName] = self::cookieValue('recent-click-id', $maxAgeDays - 1);

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('recent-click-id', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public function malformedCookieProvider(): array
    {
        return [
            'legacy unversioned value' => ['raw-legacy-click-id'],
            'missing click id' => ['v2|1700000000000'],
            'empty click id' => ['v2|1700000000000|'],
            'non-numeric timestamp' => ['v2|yesterday|click-id'],
            'zero timestamp' => ['v2|0|click-id'],
            'wrong version' => ['v3|1700000000000|click-id'],
            'control characters only' => ["v2|1700000000000|\x01\x02"],
            'empty cookie' => [''],
        ];
    }

    /**
     * @dataProvider malformedCookieProvider
     */
    public function testMalformedCookieIsIgnored(string $raw): void
    {
        foreach ($this->persistedClickIdProvider() as [$param, $cookieName]) {
            $_COOKIE[$cookieName] = $raw;
        }

        $this->createAxiTrace()->startTrial('free');

        $params = $this->lastRequestParams();
        foreach ($this->persistedClickIdProvider() as [$param]) {
            $this->assertArrayNotHasKey($param, $params);
        }
    }

    public function testArrayCookieIsIgnored(): void
    {
        $_COOKIE['_ttclid'] = ['v2|1700000000000|click-id'];

        $this->createAxiTrace()->startTrial('free');

        $this->assertArrayNotHasKey('ttclid', $this->lastRequestParams());
    }

    public function testCookieClickIdIsSanitized(): void
    {
        $_COOKIE['_ttclid'] = self::cookieValue("  click\x00-id\x1F  ");

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('click-id', $this->lastRequestParams()['ttclid'] ?? null);
    }

    public function testCookieClickIdIsTruncatedTo500Characters(): void
    {
        $_COOKIE['_gclid'] = self::cookieValue(str_repeat('a', 600));

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame(500, strlen((string) ($this->lastRequestParams()['gclid'] ?? '')));
    }

    /**
     * A malformed cookie must not block the URL value, and a valid cookie for one
     * platform must not leak into another platform's key.
     */
    public function testEachCookieFeedsOnlyItsOwnParam(): void
    {
        $_COOKIE['_ttclid'] = self::cookieValue('tiktok-click');
        $_COOKIE['_gclid'] = 'raw-legacy-gclid';
        $_GET['gclid'] = 'url-gclid';

        $this->createAxiTrace()->startTrial('free');

        $params = $this->lastRequestParams();
        $this->assertSame('tiktok-click', $params['ttclid'] ?? null);
        $this->assertSame('url-gclid', $params['gclid'] ?? null);
        $this->assertArrayNotHasKey('gbraid', $params);
        $this->assertArrayNotHasKey('rdt_cid', $params);
    }

    /**
     * Platform pixel browser ids the JavaScript SDK also forwards: the Reddit Pixel's
     * _rdt_uuid and the OpenAI Ads pixel's __obref. They are opaque values written by
     * the pixels themselves, so they are passed on as they are.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public function pixelBrowserIdProvider(): array
    {
        return [
            'Reddit _rdt_uuid' => ['_rdt_uuid', 'rdt_uuid', '1727000000000.1f9b8c2e-4d3a-4b5c-9e8f-0a1b2c3d4e5f'],
            'OpenAI Ads __obref' => ['__obref', 'obref', 'ob.1.1727000000.abcdef'],
        ];
    }

    /**
     * @dataProvider pixelBrowserIdProvider
     */
    public function testPixelBrowserIdCookieIsForwarded(string $cookieName, string $param, string $value): void
    {
        $_COOKIE[$cookieName] = $value;

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame($value, $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider pixelBrowserIdProvider
     */
    public function testExplicitPixelBrowserIdOverridesCookie(string $cookieName, string $param, string $value): void
    {
        $_COOKIE[$cookieName] = $value;

        $this->createAxiTrace()->startTrial('free', [$param => 'explicit-value']);

        $this->assertSame('explicit-value', $this->lastRequestParams()[$param] ?? null);
    }

    public function testSnapchatCapitalisedUrlParamIsReadAsSccid(): void
    {
        $_GET['ScCid'] = 'snap-click';
        $_COOKIE['_axi_sccid'] = self::cookieValue('snap-cookie');

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('snap-click', $this->lastRequestParams()['sccid'] ?? null);
    }

    public function testSnapchatCapitalisedUrlParamWinsOverLowercase(): void
    {
        $_GET['ScCid'] = 'snap-capital';
        $_GET['sccid'] = 'snap-lower';

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('snap-capital', $this->lastRequestParams()['sccid'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    public function vendorCookieProvider(): array
    {
        return [
            'UET with _uet prefix' => ['msclkid', '_uetmsclkid', '_uetabc123', 'abc123'],
            'UET bare' => ['msclkid', '_uetmsclkid', 'abc123', 'abc123'],
            'X pixel JSON' => ['twclid', '_twclid', '{"twclid":"x-click","timestamp":1}', 'x-click'],
            'X server-side tag bare' => ['twclid', '_twclid', 'x-click', 'x-click'],
            'Pinterest' => ['epik', '_epik', 'dj0yJnU9abc', 'dj0yJnU9abc'],
            'LinkedIn Insight Tag' => ['li_fat_id', 'li_fat_id', 'li-uuid-1', 'li-uuid-1'],
        ];
    }

    /**
     * @dataProvider vendorCookieProvider
     */
    public function testPlatformCookieIsTheLastFallback(string $param, string $cookieName, string $raw, string $expected): void
    {
        $_COOKIE[$cookieName] = $raw;

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame($expected, $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider vendorCookieProvider
     */
    public function testAxiTraceCookieWinsOverPlatformCookie(string $param, string $cookieName, string $raw, string $expected): void
    {
        $_COOKIE[$cookieName] = $raw;
        $_COOKIE['_axi_' . $param] = self::cookieValue('from-axitrace-cookie');

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('from-axitrace-cookie', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * @dataProvider vendorCookieProvider
     */
    public function testUrlWinsOverPlatformCookie(string $param, string $cookieName, string $raw, string $expected): void
    {
        $_COOKIE[$cookieName] = $raw;
        $_GET[$param] = 'from-url';

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('from-url', $this->lastRequestParams()[$param] ?? null);
    }

    /**
     * An AxiTrace cookie past its window does not block the platform cookie.
     */
    public function testExpiredAxiTraceCookieFallsBackToPlatformCookie(): void
    {
        $_COOKIE['_axi_epik'] = self::cookieValue('stale', 61);
        $_COOKIE['_epik'] = 'fresh-epik';

        $this->createAxiTrace()->startTrial('free');

        $this->assertSame('fresh-epik', $this->lastRequestParams()['epik'] ?? null);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public function unusablePlatformCookieProvider(): array
    {
        return [
            'X invalid JSON' => ['_twclid', '{not json'],
            'X JSON without twclid' => ['_twclid', '{"other":"x"}'],
            'UET prefix only' => ['_uetmsclkid', '_uet'],
            'blank' => ['_epik', '   '],
            'longer than 500 characters' => ['li_fat_id', str_repeat('a', 501)],
        ];
    }

    /**
     * @dataProvider unusablePlatformCookieProvider
     */
    public function testUnusablePlatformCookieIsIgnored(string $cookieName, string $raw): void
    {
        $_COOKIE[$cookieName] = $raw;

        $this->createAxiTrace()->startTrial('free');

        $params = $this->lastRequestParams();
        foreach (['msclkid', 'twclid', 'epik', 'li_fat_id'] as $param) {
            $this->assertArrayNotHasKey($param, $params);
        }
    }
}
