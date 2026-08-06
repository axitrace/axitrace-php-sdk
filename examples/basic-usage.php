<?php

/**
 * AxiTrace PHP SDK - Basic Usage Example
 *
 * Demonstrates SDK initialization plus page view and product view tracking using
 * the AxiTrace facade methods (the recommended entry point - they build the
 * underlying event objects, apply identifiers, validate, and send in one call).
 *
 * This script runs from the CLI, so $_COOKIE/$_SERVER are empty and the SDK cannot
 * auto-detect a visitor. In a real web request the vt_vid/vt_sid cookies and the
 * client IP/user agent are picked up automatically - you would not normally call
 * setClientId() yourself. If you ever need to send an event OUTSIDE a web request
 * (a queue job, a cron script, a webhook handler), see queue-transaction.php.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;

// Initialize the SDK with your secret key
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Every event needs at least one identifier (client ID, session ID, or user ID).
// In a web request this normally comes from the vt_vid cookie automatically.
$axiTrace->setClientId('visitor-' . uniqid());

// Example 1: Track a page view
try {
    $response = $axiTrace->pageView('https://example.com/products', [
        'title' => 'Our Products',
        'referrer' => 'https://google.com',
    ]);

    echo $response->isSuccess()
        ? "Page view tracked! Event ID: " . $response->getEventId() . "\n"
        : "Page view rejected by API: " . $response->getError() . "\n";
} catch (AxiTraceException $e) {
    // ValidationException here means the SDK caught a malformed event locally,
    // before any HTTP call was made.
    echo "Error tracking page view: " . $e->getMessage() . "\n";
}

// Example 2: Track a product view
try {
    $response = $axiTrace->productView([
        'item_id' => 'SKU-001',
        'item_name' => 'Wireless Headphones',
        'price' => 79.99,
        'currency' => 'USD',
        'item_brand' => 'AudioTech',
        'item_category' => 'Electronics',
    ]);

    echo $response->isSuccess()
        ? "Product view tracked! Event ID: " . $response->getEventId() . "\n"
        : "Product view rejected by API: " . $response->getError() . "\n";
} catch (AxiTraceException $e) {
    echo "Error tracking product view: " . $e->getMessage() . "\n";
}
