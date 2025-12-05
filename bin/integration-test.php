#!/usr/bin/env php
<?php

/**
 * AxiTrace PHP SDK - Integration Test Script
 *
 * This script tests all events against the real API with both valid and invalid data.
 *
 * Usage:
 *   php bin/integration-test.php
 *
 * Environment:
 *   Set AXITRACE_SECRET_KEY environment variable or edit the key below.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Config;
use AxiTrace\Model\Event\PageViewEvent;
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Event\AddToCartEvent;
use AxiTrace\Model\Event\RemoveFromCartEvent;
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Event\AddShippingInfoEvent;
use AxiTrace\Model\Event\AddPaymentInfoEvent;
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Event\SubscribeEvent;
use AxiTrace\Model\Event\StartTrialEvent;
use AxiTrace\Model\Event\SearchEvent;
use AxiTrace\Model\Event\FormSubmitEvent;
use AxiTrace\Model\Event\ViewItemListEvent;
use AxiTrace\Model\Event\SelectItemEvent;
use AxiTrace\Model\Product;
use AxiTrace\Exception\ValidationException;

// Configuration - Get secret key from environment variable
$secretKey = getenv('AXITRACE_SECRET_KEY');
if (!$secretKey) {
    echo "ERROR: AXITRACE_SECRET_KEY environment variable is required.\n";
    echo "Usage: AXITRACE_SECRET_KEY=sk_live_xxx php bin/integration-test.php\n";
    exit(1);
}
$baseUrl = getenv('AXITRACE_BASE_URL') ?: 'https://stat.axitrace.com';

// Test tracking
$testsPassed = 0;
$testsFailed = 0;
$testsTotal = 0;

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
        'reset' => "\033[0m",
    ];

    return ($colors[$color] ?? '') . $text . $colors['reset'];
}

/**
 * Print test result
 */
function printResult(string $testName, bool $passed, string $details = ''): void
{
    global $testsPassed, $testsFailed, $testsTotal;
    $testsTotal++;

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
}

/**
 * Print section header
 */
function printSection(string $title): void
{
    echo "\n" . colorize("═══ $title ═══", 'blue') . "\n\n";
}

// Initialize SDK
echo colorize("\n╔════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║        AxiTrace PHP SDK - Integration Test Suite           ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════╝", 'blue') . "\n";

echo "\nConfiguration:\n";
echo "  Secret Key: " . substr($secretKey, 0, 20) . "...\n";
echo "  Base URL: $baseUrl\n";

try {
    $config = new Config($secretKey);
    $config->setBaseUrl($baseUrl);
    $config->setTimeout(30);

    $axiTrace = new AxiTrace($config);
    echo colorize("\n  ✓ SDK initialized successfully\n", 'green');
} catch (\Exception $e) {
    echo colorize("\n  ✗ Failed to initialize SDK: " . $e->getMessage() . "\n", 'red');
    exit(1);
}

// Generate unique identifiers for this test run
$testRunId = 'test-' . date('Ymd-His') . '-' . substr(uniqid(), -4);
$visitorId = "visitor-$testRunId";
$sessionId = "session-$testRunId";
$userId = "user-$testRunId";

echo "  Test Run ID: $testRunId\n";

// Set default identifiers on the client
$axiTrace->setClientId($visitorId)->setSessionId($sessionId);

// ═══════════════════════════════════════════════════════════════════════════════
// VALID DATA TESTS
// ═══════════════════════════════════════════════════════════════════════════════

printSection("VALID DATA TESTS");

// Test 1: Page View (using shorthand method)
echo "1. Page View Event\n";
try {
    $response = $axiTrace->pageView('https://example.com/test-page', [
        'title' => 'Integration Test Page',
        'referrer' => 'https://google.com',
    ]);
    printResult("Valid page view", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid page view", false, $e->getMessage());
}

// Test 2: Product View
echo "\n2. Product View Event\n";
try {
    $product = (new Product('TEST-SKU-001'))
        ->setItemName('Test Product')
        ->setPrice(99.99)
        ->setItemBrand('TestBrand')
        ->setItemCategory('Electronics')
        ->setQuantity(1);

    $response = $axiTrace->productView($product);
    printResult("Valid product view", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid product view", false, $e->getMessage());
}

// Test 3: Add to Cart
echo "\n3. Add to Cart Event\n";
try {
    $product = (new Product('TEST-SKU-001'))
        ->setItemName('Test Product')
        ->setPrice(99.99)
        ->setQuantity(2);

    $response = $axiTrace->addToCart(199.98, 'USD', [$product]);
    printResult("Valid add to cart", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid add to cart", false, $e->getMessage());
}

// Test 4: Remove from Cart
echo "\n4. Remove from Cart Event\n";
try {
    $product = (new Product('TEST-SKU-001'))
        ->setItemName('Test Product')
        ->setPrice(99.99)
        ->setQuantity(1);

    $response = $axiTrace->removeFromCart(99.99, 'USD', [$product]);
    printResult("Valid remove from cart", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid remove from cart", false, $e->getMessage());
}

// Test 5: Begin Checkout
echo "\n5. Begin Checkout Event\n";
try {
    $items = [
        (new Product('TEST-SKU-001'))->setItemName('Test Product 1')->setPrice(99.99)->setQuantity(1),
        (new Product('TEST-SKU-002'))->setItemName('Test Product 2')->setPrice(99.99)->setQuantity(1),
    ];

    $response = $axiTrace->beginCheckout(199.98, 'USD', $items, ['coupon' => 'TESTCOUPON']);
    printResult("Valid begin checkout", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid begin checkout", false, $e->getMessage());
}

// Test 6: Add Shipping Info
echo "\n6. Add Shipping Info Event\n";
try {
    $items = [
        (new Product('TEST-SKU-001'))->setItemName('Test Product')->setPrice(199.98),
    ];

    $response = $axiTrace->addShippingInfo(199.98, 'USD', $items, [
        'shipping_tier' => 'express',
        'coupon' => 'TESTCOUPON',
    ]);
    printResult("Valid add shipping info", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid add shipping info", false, $e->getMessage());
}

// Test 7: Add Payment Info
echo "\n7. Add Payment Info Event\n";
try {
    $items = [
        (new Product('TEST-SKU-001'))->setItemName('Test Product')->setPrice(199.98),
    ];

    $response = $axiTrace->addPaymentInfo(199.98, 'USD', $items, [
        'payment_type' => 'credit_card',
        'coupon' => 'TESTCOUPON',
    ]);
    printResult("Valid add payment info", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid add payment info", false, $e->getMessage());
}

// Test 8: Transaction
echo "\n8. Transaction Event\n";
try {
    $transactionId = 'ORDER-' . $testRunId;

    // Transaction requires 'sku' field instead of 'item_id'
    $products = [
        ['sku' => 'TEST-SKU-001', 'name' => 'Test Product 1', 'regularUnitPrice' => 99.99, 'finalUnitPrice' => 99.99, 'quantity' => 1],
        ['sku' => 'TEST-SKU-002', 'name' => 'Test Product 2', 'regularUnitPrice' => 99.99, 'finalUnitPrice' => 99.99, 'quantity' => 1],
    ];

    $response = $axiTrace->transaction(
        $transactionId,
        224.98, // revenue
        199.98, // value
        'USD',
        'CARD',
        $products,
        ['email' => 'test@example.com']
    );
    printResult("Valid transaction", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid transaction", false, $e->getMessage());
}

// Test 9: Subscribe
echo "\n9. Subscribe Event\n";
try {
    $response = $axiTrace->subscribe('subscriber-' . $testRunId . '@example.com', [
        'subscription_type' => 'newsletter',
    ]);
    printResult("Valid subscribe", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid subscribe", false, $e->getMessage());
}

// Test 10: Start Trial
echo "\n10. Start Trial Event\n";
try {
    $response = $axiTrace->startTrial('Professional', [
        'trial_period_days' => 14,
        'trial_value' => 49.99,
        'trial_currency' => 'USD',
        'email' => 'trial-' . $testRunId . '@example.com',
    ]);
    printResult("Valid start trial", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid start trial", false, $e->getMessage());
}

// Test 11: Search
echo "\n11. Search Event\n";
try {
    $response = $axiTrace->search('integration test query', [
        'results_count' => 42,
        'category' => 'Electronics',
        'filters' => ['brand' => 'TestBrand', 'price_max' => 200],
        'sort_by' => 'relevance',
        'page' => 1,
    ]);
    printResult("Valid search", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid search", false, $e->getMessage());
}

// Test 12: Form Submit
echo "\n12. Form Submit Event\n";
try {
    $response = $axiTrace->formSubmit('contact-form', [
        'email' => 'form-' . $testRunId . '@example.com',
        'first_name' => 'Test',
        'last_name' => 'User',
        'message' => 'This is an integration test message.',
    ]);
    printResult("Valid form submit", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid form submit", false, $e->getMessage());
}

// Test 13: View Item List
echo "\n13. View Item List Event\n";
try {
    $items = [
        ['item_id' => 'TEST-SKU-001', 'item_name' => 'Product 1', 'price' => 99.99, 'index' => 0],
        ['item_id' => 'TEST-SKU-002', 'item_name' => 'Product 2', 'price' => 149.99, 'index' => 1],
        ['item_id' => 'TEST-SKU-003', 'item_name' => 'Product 3', 'price' => 199.99, 'index' => 2],
    ];

    $response = $axiTrace->viewItemList([
        'item_list_id' => 'test-category',
        'item_list_name' => 'Test Category',
        'items' => $items,
    ]);
    printResult("Valid view item list", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid view item list", false, $e->getMessage());
}

// Test 14: Select Item
echo "\n14. Select Item Event\n";
try {
    $product = (new Product('TEST-SKU-001'))
        ->setItemName('Product 1')
        ->setPrice(99.99)
        ->setIndex(0);

    $response = $axiTrace->selectItem($product, [
        'item_list_id' => 'test-category',
        'item_list_name' => 'Test Category',
    ]);
    printResult("Valid select item", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Valid select item", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// VALID DATA TESTS - Using Event Objects Directly
// ═══════════════════════════════════════════════════════════════════════════════

printSection("VALID DATA TESTS - Direct Event Objects");

// Test 15: Page View with Event Object
echo "15. Page View (Event Object)\n";
try {
    $event = (new PageViewEvent('https://example.com/event-test'))
        ->setClientId($visitorId)
        ->setSessionId($sessionId)
        ->setTitle('Direct Event Test')
        ->setReferrer('https://direct.test');

    $response = $axiTrace->track($event);
    printResult("Page view with event object", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Page view with event object", false, $e->getMessage());
}

// Test 16: Transaction with Event Object
echo "\n16. Transaction (Event Object)\n";
try {
    $event = TransactionEvent::create(
        'DIRECT-ORDER-' . $testRunId,
        149.99, // revenue
        149.99, // value
        'USD',
        'CARD'
    );

    $event->setClientCustomId($visitorId);
    $event->setClientEmail('direct-test@example.com');
    $event->setProducts([
        ['sku' => 'DIRECT-SKU-001', 'name' => 'Direct Test Product', 'regularUnitPrice' => 149.99, 'finalUnitPrice' => 149.99, 'quantity' => 1],
    ]);

    $response = $axiTrace->events()->send($event);
    printResult("Transaction with event object", $response->isSuccess(), $response->isSuccess() ? "Event ID: " . $response->getEventId() : $response->getError());
} catch (\Exception $e) {
    printResult("Transaction with event object", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// INVALID DATA TESTS (Validation Errors)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("INVALID DATA TESTS (Validation Errors)");

// Test 17: Page View without user identifier
echo "17. Page View - Missing User Identifier\n";
try {
    $event = new PageViewEvent('https://example.com/test-page');
    // Not setting any user identifier

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches missing user ID", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches missing user ID", false, get_class($e) . ": " . $e->getMessage());
}

// Test 18: Transaction without transaction ID
echo "\n18. Transaction - Missing Transaction ID\n";
try {
    $event = TransactionEvent::create('', 99.99, 99.99, 'USD', 'CARD');
    $event->setClientCustomId($visitorId);
    $event->setProducts([['sku' => 'SKU-001', 'name' => 'Test', 'regularUnitPrice' => 99.99, 'finalUnitPrice' => 99.99, 'quantity' => 1]]);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches missing transaction ID", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches missing transaction ID", false, get_class($e) . ": " . $e->getMessage());
}

// Test 19: Transaction without items
echo "\n19. Transaction - Missing Items\n";
try {
    $event = TransactionEvent::create('ORDER-123', 99.99, 99.99, 'USD', 'CARD');
    $event->setClientCustomId($visitorId);
    // Not setting any products

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches missing items", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches missing items", false, get_class($e) . ": " . $e->getMessage());
}

// Test 20: Transaction with negative value
echo "\n20. Transaction - Negative Value\n";
try {
    $event = TransactionEvent::create('ORDER-123', -99.99, -99.99, 'USD', 'CARD');
    $event->setClientCustomId($visitorId);
    $event->setProducts([['sku' => 'SKU-001', 'name' => 'Test', 'regularUnitPrice' => 99.99, 'finalUnitPrice' => 99.99, 'quantity' => 1]]);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches negative value", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches negative value", false, get_class($e) . ": " . $e->getMessage());
}

// Test 21: Begin Checkout without items
echo "\n21. Begin Checkout - Empty Items Array\n";
try {
    $event = (new BeginCheckoutEvent('USD', 99.99))
        ->setClientId($visitorId);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches empty items", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches empty items", false, get_class($e) . ": " . $e->getMessage());
}

// Test 22: Search without search term
echo "\n22. Search - Empty Search Term\n";
try {
    $event = (new SearchEvent(''))
        ->setClientId($visitorId);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches empty search term", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches empty search term", false, get_class($e) . ": " . $e->getMessage());
}

// Test 23: Form Submit without params
echo "\n23. Form Submit - Missing Params\n";
try {
    $event = (new FormSubmitEvent('contact-form'))
        ->setClientCustomId($visitorId);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches missing params", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches missing params", false, get_class($e) . ": " . $e->getMessage());
}

// Test 24: Form Submit with invalid email
echo "\n24. Form Submit - Invalid Email in Params\n";
try {
    $event = (new FormSubmitEvent('contact-form'))
        ->setClientCustomId($visitorId)
        ->setFormParams(['email' => 'not-a-valid-email']);

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches invalid email", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches invalid email", false, get_class($e) . ": " . $e->getMessage());
}

// Test 25: Invalid currency code
echo "\n25. Add to Cart - Invalid Currency\n";
try {
    $event = (new AddToCartEvent('INVALID', 99.99))
        ->setClientId($visitorId)
        ->addItem(new Product('SKU-001'));

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches invalid currency", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches invalid currency", false, get_class($e) . ": " . $e->getMessage());
}

// Test 26: View Item List without items
echo "\n26. View Item List - Empty Items\n";
try {
    $event = (new ViewItemListEvent())
        ->setClientId($visitorId)
        ->setItemListId('category-123');

    $event->validate();
    printResult("Should throw ValidationException", false, "No exception thrown");
} catch (ValidationException $e) {
    printResult("Validation catches empty items", true, "ValidationException: " . $e->getMessage());
} catch (\Exception $e) {
    printResult("Validation catches empty items", false, get_class($e) . ": " . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// API ERROR TESTS (Invalid Authentication)
// ═══════════════════════════════════════════════════════════════════════════════

printSection("API ERROR TESTS (Invalid Authentication)");

// Test 27: Invalid API key
echo "27. Invalid API Key\n";
try {
    $invalidConfig = new Config('sk_live_invalid_key_12345');
    $invalidConfig->setBaseUrl($baseUrl);

    $invalidClient = new AxiTrace($invalidConfig);
    $invalidClient->setClientId($visitorId);

    $response = $invalidClient->pageView('https://example.com/invalid-key-test');
    printResult("API rejects invalid key", !$response->isSuccess(), $response->isSuccess() ? "Unexpectedly succeeded" : "Error: " . $response->getError());
} catch (\Exception $e) {
    printResult("API rejects invalid key", true, "Exception: " . $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// SUMMARY
// ═══════════════════════════════════════════════════════════════════════════════

printSection("TEST SUMMARY");

$passRate = $testsTotal > 0 ? round(($testsPassed / $testsTotal) * 100, 1) : 0;

echo "Total Tests:  $testsTotal\n";
echo colorize("Passed:       $testsPassed", 'green') . "\n";
echo colorize("Failed:       $testsFailed", $testsFailed > 0 ? 'red' : 'green') . "\n";
echo "Pass Rate:    $passRate%\n";

if ($testsFailed === 0) {
    echo "\n" . colorize("✓ All tests passed!", 'green') . "\n\n";
    exit(0);
} else {
    echo "\n" . colorize("✗ Some tests failed!", 'red') . "\n\n";
    exit(1);
}
