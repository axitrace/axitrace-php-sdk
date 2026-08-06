<?php

/**
 * AxiTrace PHP SDK - Sending Events Outside a Web Request (queue, cron, webhook)
 *
 * The biggest silent trap in server-side tracking: transaction() (and every other
 * facade method) resolves the visitor via getVisitorId()/getSessionId(), which fall
 * back to reading $_COOKIE through CookieHelper. Outside a normal web request
 * (a queue worker, a cron job, a payment webhook handler run from CLI) $_COOKIE is
 * empty. The event is still ACCEPTED by the API (HTTP 200), but ships with no
 * client identifier and no session ID, and with the server's IP/user agent instead
 * of the visitor's - so the conversion is silently unattributed. Nothing errors;
 * you just never see the conversion linked to the visitor's session.
 *
 * The fix: capture the visitor context (vt_vid/vt_sid cookies, IP, user agent) at
 * the point the visitor is on your site - e.g. store them on the order/payment
 * record at checkout - then replay them via withContext() before sending the event
 * from the queue/cron job.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Simulates data captured client-side at checkout and persisted on the order record
// (e.g. read from $_COOKIE['vt_vid'] / $_COOKIE['vt_sid'] and $request->getClientIp() /
// $request->headers->get('User-Agent') during the original HTTP request, then saved
// alongside the order so a later job can replay them).
$storedVisitorContext = [
    'clientId' => 'vt-vid-abc123',
    'sessionId' => 'vt-sid-def456',
    'ip' => '203.0.113.42',
    'userAgent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
];

$orderId = 'ORDER-' . date('Ymd') . '-' . uniqid();

try {
    $response = $axiTrace
        ->withContext($storedVisitorContext)
        ->transaction(
            $orderId,
            299.99, // revenue
            279.99, // value (after discounts)
            'USD',
            'CARD',
            [
                [
                    'sku' => 'SKU-001',
                    'name' => 'Product 1',
                    'finalUnitPrice' => 279.99,
                    'quantity' => 1,
                ],
            ],
            [
                'email' => 'customer@example.com',
                // Payment webhooks are frequently retried by the provider - eventSalt is
                // the only guard against double-counting the same order.
                'event_salt' => $orderId,
            ]
        );

    echo $response->isSuccess()
        ? "Transaction tracked from queue job! Order ID: $orderId, Event ID: " . $response->getEventId() . "\n"
        : "Transaction rejected by API: " . $response->getError() . "\n";
} catch (AxiTraceException $e) {
    echo "Error tracking transaction: " . $e->getMessage() . "\n";
}
