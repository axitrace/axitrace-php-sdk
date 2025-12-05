#!/usr/bin/env php
<?php

/**
 * AxiTrace PHP SDK - Comprehensive API Test Script
 *
 * This script tests ALL 14 event types against the real API
 * using raw HTTP requests to verify the exact expected format.
 *
 * Usage: AXITRACE_SECRET_KEY=sk_live_xxx php bin/comprehensive-api-test.php
 */

// Configuration - Get secret key from environment variable
$secretKey = getenv('AXITRACE_SECRET_KEY');
if (!$secretKey) {
    echo "ERROR: AXITRACE_SECRET_KEY environment variable is required.\n";
    echo "Usage: AXITRACE_SECRET_KEY=sk_live_xxx php bin/comprehensive-api-test.php\n";
    exit(1);
}
$baseUrl = 'https://stat.axitrace.com';

// Test tracking
$testsPassed = 0;
$testsFailed = 0;
$testsTotal = 0;
$testResults = [];

// Generate unique IDs for this test run
$testRunId = 'test-' . date('Ymd-His') . '-' . substr(uniqid(), -4);
$visitorId = "visitor-$testRunId";
$sessionId = "session-$testRunId";
$userId = "user-$testRunId";

/**
 * Make HTTP request to API
 */
function makeRequest(string $endpoint, array $data, string $secretKey, string $baseUrl): array
{
    $url = $baseUrl . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($secretKey . ':'),
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'http_code' => $httpCode,
        'response' => $response,
        'error' => $error,
        'data' => json_decode($response, true),
    ];
}

/**
 * Print colored output
 */
function colorize(string $text, string $color): string
{
    $colors = [
        'green' => "\033[32m",
        'red' => "\033[31m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
        'white' => "\033[37m",
        'reset' => "\033[0m",
    ];
    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

/**
 * Print test result
 */
function printResult(string $testName, bool $passed, string $details = '', array $requestData = [], array $response = []): void
{
    global $testsPassed, $testsFailed, $testsTotal, $testResults;
    $testsTotal++;

    $result = [
        'name' => $testName,
        'passed' => $passed,
        'details' => $details,
        'request' => $requestData,
        'response' => $response,
    ];
    $testResults[] = $result;

    if ($passed) {
        $testsPassed++;
        echo colorize("  ✓ ", 'green') . $testName;
    } else {
        $testsFailed++;
        echo colorize("  ✗ ", 'red') . $testName;
    }

    if ($details) {
        echo colorize(" - $details", $passed ? 'green' : 'red');
    }

    echo "\n";

    if (!$passed && !empty($response)) {
        echo colorize("    Request: ", 'yellow') . json_encode($requestData, JSON_PRETTY_PRINT) . "\n";
        echo colorize("    Response: ", 'yellow') . ($response['response'] ?? 'N/A') . "\n";
    }
}

/**
 * Print section header
 */
function printSection(string $title): void
{
    echo "\n" . colorize("═══════════════════════════════════════════════════════════════", 'blue') . "\n";
    echo colorize("  $title", 'cyan') . "\n";
    echo colorize("═══════════════════════════════════════════════════════════════", 'blue') . "\n\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// START TESTS
// ═══════════════════════════════════════════════════════════════════════════════

echo colorize("\n╔════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║   AxiTrace PHP SDK - Comprehensive API Integration Tests          ║", 'blue') . "\n";
echo colorize("║   Testing ALL 14 Event Types Against Production API               ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

echo "\nConfiguration:\n";
echo "  Secret Key: " . substr($secretKey, 0, 20) . "...\n";
echo "  Base URL: $baseUrl\n";
echo "  Test Run ID: $testRunId\n";
echo "  Visitor ID: $visitorId\n";

// ═══════════════════════════════════════════════════════════════════════════════
// 1. PAGE VIEW - /v1/page/view
// ═══════════════════════════════════════════════════════════════════════════════

printSection("1. PAGE VIEW EVENT (/v1/page/view)");

$pageViewData = [
    'label' => 'page_view',
    'client' => [
        'customId' => $visitorId,
    ],
    'sessionId' => $sessionId,
    'params' => [
        'url' => 'https://example.com/test-page-' . $testRunId,
        'title' => 'Test Page Title',
        'referrer' => 'https://google.com',
    ],
];

$result = makeRequest('/v1/page/view', $pageViewData, $secretKey, $baseUrl);
printResult(
    "Page View with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Connection error'),
    $pageViewData,
    $result
);

// Test with minimal data
$pageViewMinimal = [
    'label' => 'page_view',
    'client' => ['customId' => $visitorId],
    'params' => ['url' => 'https://example.com/minimal-test'],
];

$result = makeRequest('/v1/page/view', $pageViewMinimal, $secretKey, $baseUrl);
printResult(
    "Page View with minimal data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $pageViewMinimal,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 2. PRODUCT VIEW - /v1/product/view
// ═══════════════════════════════════════════════════════════════════════════════

printSection("2. PRODUCT VIEW EVENT (/v1/product/view)");

$productViewData = [
    'label' => 'product_view',
    'client' => [
        'customId' => $visitorId,
    ],
    'sessionId' => $sessionId,
    'params' => [
        'sku' => 'TEST-SKU-001',
        'name' => 'Test Product',
        'price' => 99.99,
        'currency' => 'USD',
        'category' => 'Electronics',
        'brand' => 'TestBrand',
    ],
];

$result = makeRequest('/v1/product/view', $productViewData, $secretKey, $baseUrl);
printResult(
    "Product View with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $productViewData,
    $result
);

// Minimal product view
$productViewMinimal = [
    'label' => 'product_view',
    'client' => ['customId' => $visitorId],
    'params' => ['sku' => 'TEST-SKU-002'],
];

$result = makeRequest('/v1/product/view', $productViewMinimal, $secretKey, $baseUrl);
printResult(
    "Product View with minimal data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $productViewMinimal,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 3. ADD TO CART - /v1/product/addToCart
// ═══════════════════════════════════════════════════════════════════════════════

printSection("3. ADD TO CART EVENT (/v1/product/addToCart)");

$addToCartData = [
    'label' => 'add_to_cart',
    'client' => [
        'customId' => $visitorId,
    ],
    'sessionId' => $sessionId,
    'params' => [
        'sku' => 'TEST-SKU-001',
        'name' => 'Test Product',
        'quantity' => 2,
        'finalUnitPrice' => [
            'amount' => 99.99,
            'currency' => 'USD',
        ],
        'regularUnitPrice' => [
            'amount' => 119.99,
            'currency' => 'USD',
        ],
    ],
];

$result = makeRequest('/v1/product/addToCart', $addToCartData, $secretKey, $baseUrl);
printResult(
    "Add to Cart with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $addToCartData,
    $result
);

// Minimal add to cart
$addToCartMinimal = [
    'label' => 'add_to_cart',
    'client' => ['customId' => $visitorId],
    'params' => ['sku' => 'TEST-SKU-003', 'quantity' => 1],
];

$result = makeRequest('/v1/product/addToCart', $addToCartMinimal, $secretKey, $baseUrl);
printResult(
    "Add to Cart with minimal data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $addToCartMinimal,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 4. REMOVE FROM CART - /v1/cart/remove
// NOTE: This endpoint uses client_id at root level, not nested client object
// ═══════════════════════════════════════════════════════════════════════════════

printSection("4. REMOVE FROM CART EVENT (/v1/cart/remove)");

$removeFromCartData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'currency' => 'USD',
    'value' => 99.99,
    'items' => [
        ['item_id' => 'TEST-SKU-001', 'item_name' => 'Test Product', 'price' => 99.99, 'quantity' => 1],
    ],
];

$result = makeRequest('/v1/cart/remove', $removeFromCartData, $secretKey, $baseUrl);
printResult(
    "Remove from Cart with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $removeFromCartData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 5. BEGIN CHECKOUT - /v1/checkout/begin
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("5. BEGIN CHECKOUT EVENT (/v1/checkout/begin)");

$beginCheckoutData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'currency' => 'USD',
    'value' => 199.98,
    'coupon' => 'SAVE10',
    'items' => [
        ['item_id' => 'TEST-SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'quantity' => 1],
        ['item_id' => 'TEST-SKU-002', 'item_name' => 'Product 2', 'price' => 99.99, 'quantity' => 1],
    ],
];

$result = makeRequest('/v1/checkout/begin', $beginCheckoutData, $secretKey, $baseUrl);
printResult(
    "Begin Checkout with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $beginCheckoutData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 6. ADD SHIPPING INFO - /v1/checkout/add_shipping_info
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("6. ADD SHIPPING INFO EVENT (/v1/checkout/add_shipping_info)");

$addShippingData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'currency' => 'USD',
    'value' => 199.98,
    'shipping_tier' => 'express',
    'coupon' => 'SAVE10',
    'items' => [
        ['item_id' => 'TEST-SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'quantity' => 1],
    ],
];

$result = makeRequest('/v1/checkout/add_shipping_info', $addShippingData, $secretKey, $baseUrl);
printResult(
    "Add Shipping Info with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $addShippingData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 7. ADD PAYMENT INFO - /v1/checkout/add_payment_info
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("7. ADD PAYMENT INFO EVENT (/v1/checkout/add_payment_info)");

$addPaymentData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'currency' => 'USD',
    'value' => 199.98,
    'payment_type' => 'credit_card',
    'coupon' => 'SAVE10',
    'items' => [
        ['item_id' => 'TEST-SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'quantity' => 1],
    ],
];

$result = makeRequest('/v1/checkout/add_payment_info', $addPaymentData, $secretKey, $baseUrl);
printResult(
    "Add Payment Info with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $addPaymentData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 8. TRANSACTION - /v1/transaction
// ═══════════════════════════════════════════════════════════════════════════════

printSection("8. TRANSACTION EVENT (/v1/transaction)");

$transactionData = [
    'client' => [
        'customId' => $visitorId,
        'email' => 'test-' . $testRunId . '@example.com',
    ],
    'orderId' => 'ORDER-' . $testRunId,
    'paymentInfo' => [
        'method' => 'CARD',
    ],
    'products' => [
        [
            'sku' => 'TEST-SKU-001',
            'name' => 'Test Product 1',
            'finalUnitPrice' => [
                'amount' => 99.99,
                'currency' => 'USD',
            ],
            'quantity' => 1,
            'categories' => ['Electronics', 'Gadgets'],
        ],
        [
            'sku' => 'TEST-SKU-002',
            'name' => 'Test Product 2',
            'finalUnitPrice' => [
                'amount' => 49.99,
                'currency' => 'USD',
            ],
            'quantity' => 2,
        ],
    ],
    'revenue' => [
        'amount' => 199.97,
        'currency' => 'USD',
    ],
    'value' => [
        'amount' => 179.97,
        'currency' => 'USD',
    ],
    'source' => 'WEB_DESKTOP',
    'discountAmount' => [
        'amount' => 20.00,
        'currency' => 'USD',
    ],
    'metadata' => [
        'campaign' => 'summer_sale',
        'affiliate' => 'partner123',
    ],
];

$result = makeRequest('/v1/transaction', $transactionData, $secretKey, $baseUrl);
printResult(
    "Transaction with full data",
    $result['success'],
    $result['success'] ? "Transaction ID: " . ($result['data']['transactionId'] ?? 'N/A') . ", Order: " . ($result['data']['orderId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $transactionData,
    $result
);

// Minimal transaction
$transactionMinimal = [
    'client' => ['customId' => $visitorId],
    'orderId' => 'ORDER-MIN-' . $testRunId,
    'paymentInfo' => ['method' => 'CASH'],
    'products' => [
        [
            'sku' => 'SKU-MIN',
            'name' => 'Minimal Product',
            'finalUnitPrice' => ['amount' => 10.00, 'currency' => 'USD'],
            'quantity' => 1,
        ],
    ],
    'revenue' => ['amount' => 10.00, 'currency' => 'USD'],
    'value' => ['amount' => 10.00, 'currency' => 'USD'],
    'source' => 'WEB_DESKTOP',
];

$result = makeRequest('/v1/transaction', $transactionMinimal, $secretKey, $baseUrl);
printResult(
    "Transaction with minimal data",
    $result['success'],
    $result['success'] ? "Transaction ID: " . ($result['data']['transactionId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $transactionMinimal,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 9. SUBSCRIBE - /v1/subscribe
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("9. SUBSCRIBE EVENT (/v1/subscribe)");

$subscribeData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'email' => 'subscribe-' . $testRunId . '@example.com',
    'subscription_type' => 'newsletter',
];

$result = makeRequest('/v1/subscribe', $subscribeData, $secretKey, $baseUrl);
printResult(
    "Subscribe with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $subscribeData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 10. START TRIAL - /v1/start_trial
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("10. START TRIAL EVENT (/v1/start_trial)");

$startTrialData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'plan_name' => 'Professional',
    'trial_period_days' => 14,
    'trial_value' => 49.99,
    'trial_currency' => 'USD',
];

$result = makeRequest('/v1/start_trial', $startTrialData, $secretKey, $baseUrl);
printResult(
    "Start Trial with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $startTrialData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 11. SEARCH - /v1/search
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("11. SEARCH EVENT (/v1/search)");

$searchData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'search_term' => 'wireless headphones',
    'params' => [
        'results_count' => 42,
        'category' => 'Electronics',
        'filters' => [
            'brand' => 'Sony',
            'price_min' => 50,
            'price_max' => 200,
        ],
        'sort_by' => 'relevance',
        'page' => 1,
    ],
];

$result = makeRequest('/v1/search', $searchData, $secretKey, $baseUrl);
printResult(
    "Search with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $searchData,
    $result
);

// Minimal search
$searchMinimal = [
    'client_id' => $visitorId,
    'search_term' => 'test query',
];

$result = makeRequest('/v1/search', $searchMinimal, $secretKey, $baseUrl);
printResult(
    "Search with minimal data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $searchMinimal,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 12. FORM SUBMIT - /v1/form/submit
// ═══════════════════════════════════════════════════════════════════════════════

printSection("12. FORM SUBMIT EVENT (/v1/form/submit)");

$formSubmitData = [
    'label' => 'contact-form',
    'client' => [
        'customId' => $visitorId,
        'email' => 'form-' . $testRunId . '@example.com',
    ],
    'sessionId' => $sessionId,
    'params' => [
        'email' => 'form-' . $testRunId . '@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+1234567890',
        'message' => 'Test message from integration test',
        'company' => 'Test Company',
    ],
    'eventSalt' => 'form-' . $testRunId, // Deduplication
];

$result = makeRequest('/v1/form/submit', $formSubmitData, $secretKey, $baseUrl);
printResult(
    "Form Submit with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $formSubmitData,
    $result
);

// Lead form
$leadFormData = [
    'label' => 'lead-generation',
    'client' => [
        'customId' => $visitorId,
        'email' => 'lead-' . $testRunId . '@example.com',
    ],
    'sessionId' => $sessionId,
    'params' => [
        'email' => 'lead-' . $testRunId . '@example.com',
        'company' => 'Enterprise Corp',
        'job_title' => 'CTO',
    ],
];

$result = makeRequest('/v1/form/submit', $leadFormData, $secretKey, $baseUrl);
printResult(
    "Lead Form Submit",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $leadFormData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 13. VIEW ITEM LIST - /v1/catalog/view_list
// NOTE: Uses client_id at root level (not nested client object)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("13. VIEW ITEM LIST EVENT (/v1/catalog/view_list)");

$viewItemListData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'item_list_id' => 'category-electronics',
    'item_list_name' => 'Electronics',
    'items' => [
        ['item_id' => 'SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'index' => 0],
        ['item_id' => 'SKU-002', 'item_name' => 'Product 2', 'price' => 149.99, 'index' => 1],
        ['item_id' => 'SKU-003', 'item_name' => 'Product 3', 'price' => 199.99, 'index' => 2],
    ],
];

$result = makeRequest('/v1/catalog/view_list', $viewItemListData, $secretKey, $baseUrl);
printResult(
    "View Item List with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $viewItemListData,
    $result
);

// Search results list
$searchResultsList = [
    'client_id' => $visitorId,
    'item_list_id' => 'search-headphones',
    'item_list_name' => 'Search: headphones',
    'items' => [
        ['item_id' => 'SKU-HP-001', 'item_name' => 'Wireless Headphones', 'price' => 79.99, 'index' => 0],
    ],
];

$result = makeRequest('/v1/catalog/view_list', $searchResultsList, $secretKey, $baseUrl);
printResult(
    "View Item List (search results)",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $searchResultsList,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// 14. SELECT ITEM - /v1/catalog/select_item
// NOTE: Uses client_id at root level (not nested client object)
// NOTE: items array must contain exactly 1 item
// ═══════════════════════════════════════════════════════════════════════════════

printSection("14. SELECT ITEM EVENT (/v1/catalog/select_item)");

$selectItemData = [
    'client_id' => $visitorId,
    'session_id' => $sessionId,
    'item_list_id' => 'category-electronics',
    'item_list_name' => 'Electronics',
    'items' => [
        ['item_id' => 'SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'index' => 0],
    ],
];

$result = makeRequest('/v1/catalog/select_item', $selectItemData, $secretKey, $baseUrl);
printResult(
    "Select Item with full data",
    $result['success'],
    $result['success'] ? "Event ID: " . ($result['data']['eventId'] ?? 'N/A') : ($result['response'] ?? 'Error'),
    $selectItemData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// AUTHENTICATION TESTS
// ═══════════════════════════════════════════════════════════════════════════════

printSection("AUTHENTICATION TESTS");

// Invalid API key
$invalidResult = makeRequest('/v1/page/view', $pageViewMinimal, 'sk_live_invalid_key', $baseUrl);
printResult(
    "Invalid API key rejected",
    !$invalidResult['success'] && $invalidResult['http_code'] === 401,
    "HTTP " . $invalidResult['http_code'],
    $pageViewMinimal,
    $invalidResult
);

// No API key
$ch = curl_init($baseUrl . '/v1/page/view');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($pageViewMinimal),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

printResult(
    "No API key rejected",
    $httpCode === 401,
    "HTTP $httpCode",
    $pageViewMinimal,
    ['http_code' => $httpCode, 'response' => $response]
);

// ═══════════════════════════════════════════════════════════════════════════════
// VALIDATION TESTS
// ═══════════════════════════════════════════════════════════════════════════════

printSection("VALIDATION TESTS (Expect failures)");

// Missing label
$invalidData = [
    'client' => ['customId' => $visitorId],
    'params' => ['url' => 'https://example.com'],
];
$result = makeRequest('/v1/page/view', $invalidData, $secretKey, $baseUrl);
printResult(
    "Missing label rejected",
    !$result['success'],
    "HTTP " . $result['http_code'] . ": " . substr($result['response'], 0, 50),
    $invalidData,
    $result
);

// Missing client
$invalidData = [
    'label' => 'page_view',
    'params' => ['url' => 'https://example.com'],
];
$result = makeRequest('/v1/page/view', $invalidData, $secretKey, $baseUrl);
printResult(
    "Missing client rejected",
    !$result['success'],
    "HTTP " . $result['http_code'] . ": " . substr($result['response'], 0, 50),
    $invalidData,
    $result
);

// Missing URL for page view
$invalidData = [
    'label' => 'page_view',
    'client' => ['customId' => $visitorId],
    'params' => [],
];
$result = makeRequest('/v1/page/view', $invalidData, $secretKey, $baseUrl);
printResult(
    "Missing URL for page view rejected",
    !$result['success'],
    "HTTP " . $result['http_code'] . ": " . substr($result['response'], 0, 50),
    $invalidData,
    $result
);

// Missing SKU for product view
$invalidData = [
    'label' => 'product_view',
    'client' => ['customId' => $visitorId],
    'params' => ['name' => 'Test'],
];
$result = makeRequest('/v1/product/view', $invalidData, $secretKey, $baseUrl);
printResult(
    "Missing SKU for product view rejected",
    !$result['success'],
    "HTTP " . $result['http_code'] . ": " . substr($result['response'], 0, 50),
    $invalidData,
    $result
);

// Transaction without products
$invalidData = [
    'client' => ['customId' => $visitorId],
    'orderId' => 'ORDER-INVALID',
    'paymentInfo' => ['method' => 'CARD'],
    'products' => [],
    'revenue' => ['amount' => 100, 'currency' => 'USD'],
    'value' => ['amount' => 100, 'currency' => 'USD'],
    'source' => 'WEB_DESKTOP',
];
$result = makeRequest('/v1/transaction', $invalidData, $secretKey, $baseUrl);
printResult(
    "Transaction without products rejected",
    !$result['success'],
    "HTTP " . $result['http_code'] . ": " . substr($result['response'], 0, 50),
    $invalidData,
    $result
);

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════

printSection("TEST SUMMARY");

$passRate = $testsTotal > 0 ? round(($testsPassed / $testsTotal) * 100, 1) : 0;

echo "Total Tests:  $testsTotal\n";
echo colorize("Passed:       $testsPassed", 'green') . "\n";
echo colorize("Failed:       $testsFailed", $testsFailed > 0 ? 'red' : 'green') . "\n";
echo "Pass Rate:    $passRate%\n";

// List failed tests
if ($testsFailed > 0) {
    echo "\n" . colorize("Failed Tests:", 'red') . "\n";
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo colorize("  - " . $result['name'], 'red') . "\n";
        }
    }
}

if ($testsFailed === 0) {
    echo "\n" . colorize("✓ ALL TESTS PASSED!", 'green') . "\n\n";
    exit(0);
} else {
    echo "\n" . colorize("✗ Some tests failed!", 'red') . "\n\n";
    exit(1);
}
