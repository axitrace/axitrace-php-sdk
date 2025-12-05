#!/usr/bin/env php
<?php

/**
 * AxiTrace PHP SDK - SDK Integration Test
 *
 * This script tests the SDK classes against the real API.
 * It verifies that the SDK produces the correct format for all 14 event types.
 *
 * Usage: AXITRACE_SECRET_KEY=sk_live_xxx php bin/sdk-integration-test.php
 */

require __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Config;
use AxiTrace\Api\Response;
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
use AxiTrace\Model\Money;
use AxiTrace\Model\ClientIdentity;

// Configuration - Get secret key from environment variable
$secretKey = getenv('AXITRACE_SECRET_KEY');
if (!$secretKey) {
    echo "ERROR: AXITRACE_SECRET_KEY environment variable is required.\n";
    echo "Usage: AXITRACE_SECRET_KEY=sk_live_xxx php bin/sdk-integration-test.php\n";
    exit(1);
}

// Test tracking
$testsPassed = 0;
$testsFailed = 0;
$testsTotal = 0;
$testResults = [];

// Generate unique IDs for this test run
$testRunId = 'sdk-' . date('Ymd-His') . '-' . substr(uniqid(), -4);
$visitorId = "visitor-$testRunId";
$sessionId = "session-$testRunId";

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
function printResult(string $testName, bool $passed, string $details = ''): void
{
    global $testsPassed, $testsFailed, $testsTotal, $testResults;
    $testsTotal++;

    $testResults[] = [
        'name' => $testName,
        'passed' => $passed,
        'details' => $details,
    ];

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
    echo "\n" . colorize("═══════════════════════════════════════════════════════════════", 'blue') . "\n";
    echo colorize("  $title", 'cyan') . "\n";
    echo colorize("═══════════════════════════════════════════════════════════════", 'blue') . "\n\n";
}

// ═══════════════════════════════════════════════════════════════════════════════
// INITIALIZE SDK
// ═══════════════════════════════════════════════════════════════════════════════

echo colorize("\n╔════════════════════════════════════════════════════════════════════╗", 'blue') . "\n";
echo colorize("║   AxiTrace PHP SDK - SDK Class Integration Tests                   ║", 'blue') . "\n";
echo colorize("║   Testing ALL 14 Event Types Using SDK Classes                     ║", 'blue') . "\n";
echo colorize("╚════════════════════════════════════════════════════════════════════╝", 'blue') . "\n";

echo "\nConfiguration:\n";
echo "  Secret Key: " . substr($secretKey, 0, 20) . "...\n";
echo "  Test Run ID: $testRunId\n";
echo "  Visitor ID: $visitorId\n\n";

try {
    $config = new Config($secretKey);
    $axiTrace = new AxiTrace($config);
    echo colorize("  ✓ SDK initialized successfully\n", 'green');
} catch (\Exception $e) {
    echo colorize("  ✗ Failed to initialize SDK: " . $e->getMessage() . "\n", 'red');
    exit(1);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 1. PAGE VIEW
// ═══════════════════════════════════════════════════════════════════════════════

printSection("1. PAGE VIEW EVENT");

try {
    $event = new PageViewEvent('https://example.com/test-page-' . $testRunId);
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setTitle('Test Page Title')
          ->setReferrer('https://google.com');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Page View via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Page View via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 2. PRODUCT VIEW
// ═══════════════════════════════════════════════════════════════════════════════

printSection("2. PRODUCT VIEW EVENT");

try {
    $product = new Product('SDK-TEST-SKU-001');
    $product->setItemName('SDK Test Product')
            ->setPrice(99.99)
            ->setCurrency('USD')
            ->setItemCategory('Electronics')
            ->setItemBrand('TestBrand');

    $event = ProductViewEvent::fromArray([
        'item_id' => 'SDK-TEST-SKU-001',
        'item_name' => 'SDK Test Product',
        'price' => 99.99,
        'currency' => 'USD',
        'category' => 'Electronics',
        'brand' => 'TestBrand',
    ]);
    $event->setClientId($visitorId)
          ->setSessionId($sessionId);

    $response = $axiTrace->events()->send($event);
    printResult(
        "Product View via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Product View via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 3. ADD TO CART
// ═══════════════════════════════════════════════════════════════════════════════

printSection("3. ADD TO CART EVENT");

try {
    $product = new Product('SDK-TEST-SKU-002');
    $product->setItemName('SDK Cart Product')
            ->setPrice(49.99)
            ->setCurrency('USD')
            ->setQuantity(2);

    $event = new AddToCartEvent('USD', 99.98);
    $event->addItem($product)
          ->setClientId($visitorId)
          ->setSessionId($sessionId);

    $response = $axiTrace->events()->send($event);
    printResult(
        "Add to Cart via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Add to Cart via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 4. REMOVE FROM CART
// ═══════════════════════════════════════════════════════════════════════════════

printSection("4. REMOVE FROM CART EVENT");

try {
    $product = new Product('SDK-TEST-SKU-002');
    $product->setItemName('SDK Cart Product')
            ->setPrice(49.99)
            ->setQuantity(1);

    $event = new RemoveFromCartEvent('USD', 49.99);
    $event->addItem($product)
          ->setClientId($visitorId)
          ->setSessionId($sessionId);

    $response = $axiTrace->events()->send($event);
    printResult(
        "Remove from Cart via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Remove from Cart via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 5. BEGIN CHECKOUT
// ═══════════════════════════════════════════════════════════════════════════════

printSection("5. BEGIN CHECKOUT EVENT");

try {
    $product = new Product('SDK-TEST-SKU-003');
    $product->setItemName('Checkout Product')
            ->setPrice(199.99)
            ->setQuantity(1);

    $event = new BeginCheckoutEvent('USD', 199.99);
    $event->addItem($product)
          ->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setCoupon('SDKTEST10');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Begin Checkout via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Begin Checkout via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 6. ADD SHIPPING INFO
// ═══════════════════════════════════════════════════════════════════════════════

printSection("6. ADD SHIPPING INFO EVENT");

try {
    $product = new Product('SDK-TEST-SKU-003');
    $product->setItemName('Checkout Product')
            ->setPrice(199.99)
            ->setQuantity(1);

    $event = new AddShippingInfoEvent('USD', 199.99);
    $event->addItem($product)
          ->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setShippingTier('express');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Add Shipping Info via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Add Shipping Info via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 7. ADD PAYMENT INFO
// ═══════════════════════════════════════════════════════════════════════════════

printSection("7. ADD PAYMENT INFO EVENT");

try {
    $product = new Product('SDK-TEST-SKU-003');
    $product->setItemName('Checkout Product')
            ->setPrice(199.99)
            ->setQuantity(1);

    $event = new AddPaymentInfoEvent('USD', 199.99);
    $event->addItem($product)
          ->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setPaymentType('credit_card');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Add Payment Info via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Add Payment Info via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 8. TRANSACTION
// ═══════════════════════════════════════════════════════════════════════════════

printSection("8. TRANSACTION EVENT");

try {
    $event = TransactionEvent::create(
        'SDK-ORDER-' . $testRunId,
        199.99,
        179.99,
        'USD',
        'CARD',
        TransactionEvent::SOURCE_WEB_DESKTOP
    );

    $event->setClientCustomId($visitorId)
          ->setClientEmail('sdk-test-' . $testRunId . '@example.com')
          ->setProducts([
              [
                  'sku' => 'SDK-TEST-SKU-003',
                  'name' => 'SDK Transaction Product',
                  'finalUnitPrice' => ['amount' => 199.99, 'currency' => 'USD'],
                  'quantity' => 1,
              ],
          ])
          ->setDiscountAmount(new Money(20.00, 'USD'))
          ->setMetadata(['sdk_test' => true]);

    $response = $axiTrace->events()->send($event);
    printResult(
        "Transaction via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Transaction ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Transaction via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 9. SUBSCRIBE
// ═══════════════════════════════════════════════════════════════════════════════

printSection("9. SUBSCRIBE EVENT");

try {
    $event = new SubscribeEvent('sdk-subscribe-' . $testRunId . '@example.com');
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setSubscriptionType('newsletter');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Subscribe via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Subscribe via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 10. START TRIAL
// ═══════════════════════════════════════════════════════════════════════════════

printSection("10. START TRIAL EVENT");

try {
    $event = new StartTrialEvent('Professional');
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setTrialPeriodDays(14)
          ->setTrialValue(49.99, 'USD');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Start Trial via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Start Trial via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 11. SEARCH
// ═══════════════════════════════════════════════════════════════════════════════

printSection("11. SEARCH EVENT");

try {
    $event = new SearchEvent('SDK test query');
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setResultsCount(42)
          ->setCategory('Electronics');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Search via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Search via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 12. FORM SUBMIT
// ═══════════════════════════════════════════════════════════════════════════════

printSection("12. FORM SUBMIT EVENT");

try {
    $event = new FormSubmitEvent('sdk-contact-form');
    $event->setClientCustomId($visitorId)
          ->setClientEmail('sdk-form-' . $testRunId . '@example.com')
          ->setSessionId($sessionId)
          ->setEmail('sdk-form-' . $testRunId . '@example.com')
          ->setFormParams([
              'first_name' => 'SDK',
              'last_name' => 'Test',
              'message' => 'Test message from SDK integration test',
          ]);

    $response = $axiTrace->events()->send($event);
    printResult(
        "Form Submit via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Form Submit via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 13. VIEW ITEM LIST
// ═══════════════════════════════════════════════════════════════════════════════

printSection("13. VIEW ITEM LIST EVENT");

try {
    $product1 = new Product('SDK-LIST-001');
    $product1->setItemName('List Product 1')
             ->setPrice(99.99)
             ->setIndex(0);

    $product2 = new Product('SDK-LIST-002');
    $product2->setItemName('List Product 2')
             ->setPrice(149.99)
             ->setIndex(1);

    $event = new ViewItemListEvent();
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setItemListId('sdk-category-test')
          ->setItemListName('SDK Test Category')
          ->addItem($product1)
          ->addItem($product2);

    $response = $axiTrace->events()->send($event);
    printResult(
        "View Item List via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("View Item List via SDK", false, $e->getMessage());
}

// ═══════════════════════════════════════════════════════════════════════════════
// 14. SELECT ITEM
// ═══════════════════════════════════════════════════════════════════════════════

printSection("14. SELECT ITEM EVENT");

try {
    $product = new Product('SDK-SELECT-001');
    $product->setItemName('Selected Product')
            ->setPrice(79.99)
            ->setIndex(0);

    $event = new SelectItemEvent($product);
    $event->setClientId($visitorId)
          ->setSessionId($sessionId)
          ->setItemListId('sdk-category-test')
          ->setItemListName('SDK Test Category');

    $response = $axiTrace->events()->send($event);
    printResult(
        "Select Item via SDK",
        $response->isSuccess(),
        $response->isSuccess() ? "Event ID: " . ($response->getEventId() ?? 'N/A') : $response->getError()
    );
} catch (\Exception $e) {
    printResult("Select Item via SDK", false, $e->getMessage());
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

// List failed tests
if ($testsFailed > 0) {
    echo "\n" . colorize("Failed Tests:", 'red') . "\n";
    foreach ($testResults as $result) {
        if (!$result['passed']) {
            echo colorize("  - " . $result['name'] . ": " . $result['details'], 'red') . "\n";
        }
    }
}

if ($testsFailed === 0) {
    echo "\n" . colorize("✓ ALL SDK TESTS PASSED!", 'green') . "\n\n";
    exit(0);
} else {
    echo "\n" . colorize("✗ Some SDK tests failed!", 'red') . "\n\n";
    exit(1);
}
