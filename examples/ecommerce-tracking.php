<?php

/**
 * AxiTrace PHP SDK - E-commerce Tracking Example
 *
 * This example demonstrates the complete e-commerce tracking flow:
 * Product View -> Add to Cart -> Begin Checkout -> Add Shipping -> Add Payment -> Transaction
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Event\AddToCartEvent;
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Event\AddShippingInfoEvent;
use AxiTrace\Model\Event\AddPaymentInfoEvent;
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Product;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Simulated visitor ID (in production, use cookies)
$visitorId = 'visitor-' . uniqid();

// Define products
$product1 = (new Product('SKU-LAPTOP-001'))
    ->setItemName('Pro Laptop 15"')
    ->setPrice(1299.99)
    ->setItemBrand('TechBrand')
    ->setItemCategory('Electronics')
    ->setItemCategory2('Computers')
    ->setItemCategory3('Laptops')
    ->setQuantity(1);

$product2 = (new Product('SKU-MOUSE-001'))
    ->setItemName('Wireless Mouse')
    ->setPrice(49.99)
    ->setItemBrand('TechBrand')
    ->setItemCategory('Electronics')
    ->setItemCategory2('Accessories')
    ->setQuantity(2);

// Step 1: Product View
echo "Step 1: Product View\n";
try {
    $productViewEvent = (new ProductViewEvent('USD', 1299.99))
        ->setClientId($visitorId)
        ->addItem($product1);

    $response = $axiTrace->trackProductView($productViewEvent);
    echo "  - Product view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 2: Add to Cart
echo "\nStep 2: Add to Cart\n";
try {
    // Add first product
    $addToCartEvent1 = (new AddToCartEvent('USD', 1299.99))
        ->setClientId($visitorId)
        ->addItem($product1);

    $response = $axiTrace->trackAddToCart($addToCartEvent1);
    echo "  - Laptop added to cart: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";

    // Add second product
    $addToCartEvent2 = (new AddToCartEvent('USD', 99.98))
        ->setClientId($visitorId)
        ->addItem($product2);

    $response = $axiTrace->trackAddToCart($addToCartEvent2);
    echo "  - Mouse added to cart: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 3: Begin Checkout
echo "\nStep 3: Begin Checkout\n";
try {
    $cartTotal = 1299.99 + 99.98; // $1399.97

    $beginCheckoutEvent = (new BeginCheckoutEvent('USD', $cartTotal))
        ->setClientId($visitorId)
        ->addItem($product1)
        ->addItem($product2)
        ->setCoupon('SAVE10');

    $response = $axiTrace->trackBeginCheckout($beginCheckoutEvent);
    echo "  - Checkout started: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 4: Add Shipping Info
echo "\nStep 4: Add Shipping Info\n";
try {
    $addShippingEvent = (new AddShippingInfoEvent('USD', 1399.97))
        ->setClientId($visitorId)
        ->addItem($product1)
        ->addItem($product2)
        ->setShippingTier('express')
        ->setCoupon('SAVE10');

    $response = $axiTrace->trackAddShippingInfo($addShippingEvent);
    echo "  - Shipping info added: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 5: Add Payment Info
echo "\nStep 5: Add Payment Info\n";
try {
    $addPaymentEvent = (new AddPaymentInfoEvent('USD', 1399.97))
        ->setClientId($visitorId)
        ->addItem($product1)
        ->addItem($product2)
        ->setPaymentType('credit_card')
        ->setCoupon('SAVE10');

    $response = $axiTrace->trackAddPaymentInfo($addPaymentEvent);
    echo "  - Payment info added: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Step 6: Transaction (Purchase)
echo "\nStep 6: Transaction\n";
try {
    $transactionId = 'ORDER-' . date('Ymd') . '-' . uniqid();
    $subtotal = 1399.97;
    $discount = 139.997; // 10% off
    $shipping = 15.00;
    $tax = 126.00;
    $total = $subtotal - $discount + $shipping + $tax;

    $transactionEvent = (new TransactionEvent($transactionId, 'USD', $total))
        ->setClientId($visitorId)
        ->setUserId('user-12345') // If user logged in during checkout
        ->setEmail('customer@example.com')
        ->addItem($product1)
        ->addItem($product2)
        ->setTax($tax)
        ->setShipping($shipping)
        ->setCoupon('SAVE10')
        ->setAffiliation('Main Website');

    $response = $axiTrace->trackTransaction($transactionEvent);
    echo "  - Transaction completed: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
    if ($response->isSuccess()) {
        echo "  - Transaction ID: " . $transactionId . "\n";
        echo "  - Event ID: " . $response->getEventId() . "\n";
    }
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nE-commerce tracking flow completed!\n";
