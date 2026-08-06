<?php

/**
 * AxiTrace PHP SDK - E-commerce Tracking Example
 *
 * Demonstrates the full e-commerce tracking flow using the AxiTrace facade methods:
 * Product View -> Add to Cart -> Begin Checkout -> Add Shipping -> Add Payment -> Transaction
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Every event needs at least one identifier. In a real web request the vt_vid cookie
// supplies this automatically; this script runs from the CLI so we set it explicitly.
$visitorId = 'visitor-' . uniqid();
$axiTrace->setClientId($visitorId);

$laptop = [
    'item_id' => 'SKU-LAPTOP-001',
    'item_name' => 'Pro Laptop 15"',
    'price' => 1299.99,
    'currency' => 'USD',
    'item_brand' => 'TechBrand',
    'item_category' => 'Electronics',
    'quantity' => 1,
];

$mouse = [
    'item_id' => 'SKU-MOUSE-001',
    'item_name' => 'Wireless Mouse',
    'price' => 49.99,
    'currency' => 'USD',
    'item_brand' => 'TechBrand',
    'item_category' => 'Electronics',
    'quantity' => 2,
];

// Step 1: Product View
echo "Step 1: Product View\n";
try {
    $response = $axiTrace->productView($laptop);
    echo "  - Product view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 2: Add to Cart
echo "\nStep 2: Add to Cart\n";
try {
    $response = $axiTrace->addToCart(1299.99, 'USD', [$laptop]);
    echo "  - Laptop added to cart: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";

    $response = $axiTrace->addToCart(99.98, 'USD', [$mouse]);
    echo "  - Mouse added to cart: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 3: Begin Checkout
echo "\nStep 3: Begin Checkout\n";
$cartTotal = 1299.99 + 99.98; // $1399.97
try {
    $response = $axiTrace->beginCheckout($cartTotal, 'USD', [$laptop, $mouse], [
        'coupon' => 'SAVE10',
    ]);
    echo "  - Checkout started: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 4: Add Shipping Info
echo "\nStep 4: Add Shipping Info\n";
try {
    $response = $axiTrace->addShippingInfo($cartTotal, 'USD', [$laptop, $mouse], [
        'shipping_tier' => 'express',
        'coupon' => 'SAVE10',
    ]);
    echo "  - Shipping info added: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 5: Add Payment Info
echo "\nStep 5: Add Payment Info\n";
try {
    $response = $axiTrace->addPaymentInfo($cartTotal, 'USD', [$laptop, $mouse], [
        'payment_type' => 'credit_card',
        'coupon' => 'SAVE10',
    ]);
    echo "  - Payment info added: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 6: Transaction (Purchase)
echo "\nStep 6: Transaction\n";
try {
    $orderId = 'ORDER-' . date('Ymd') . '-' . uniqid();
    $subtotal = 1399.97;
    $discount = 139.997; // 10% off
    $shipping = 15.00;
    $tax = 126.00;
    $total = $subtotal - $discount + $shipping + $tax;

    $response = $axiTrace->transaction(
        $orderId,
        $subtotal,      // revenue
        $total,         // value (after discounts/shipping/tax)
        'USD',
        'CARD',
        [
            // "finalUnitPrice" as a bare number is the natural thing to write - the SDK
            // normalizes it into {amount, currency} using the transaction currency (USD)
            // before it ever reaches the API. Passing an already-shaped
            // ["amount" => .., "currency" => ..] array also works if you need a
            // different currency per line item.
            [
                'sku' => 'SKU-LAPTOP-001',
                'name' => 'Pro Laptop 15"',
                'finalUnitPrice' => 1299.99,
                'quantity' => 1,
            ],
            [
                'sku' => 'SKU-MOUSE-001',
                'name' => 'Wireless Mouse',
                'finalUnitPrice' => 49.99,
                'quantity' => 2,
            ],
        ],
        [
            'email' => 'customer@example.com',
            // Deduplication guard: /v1/transaction performs NO server-side dedup, so if
            // this call is retried (e.g. a webhook fires twice) without event_salt you get
            // two transactions for one order. Using the orderId is the recommended value.
            'event_salt' => $orderId,
        ]
    );

    echo "  - Transaction completed: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
    if ($response->isSuccess()) {
        echo "  - Order ID: " . $orderId . "\n";
        echo "  - Event ID: " . $response->getEventId() . "\n";
    }
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nE-commerce tracking flow completed!\n";
