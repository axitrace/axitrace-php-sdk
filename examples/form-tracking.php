<?php

/**
 * AxiTrace PHP SDK - Form Tracking Example
 *
 * Demonstrates form submission, newsletter subscription, trial start, and search
 * tracking using the AxiTrace facade methods, plus products and a value on lead events.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;
use AxiTrace\Model\Event\CustomEvent;
use AxiTrace\Model\Event\SubscribeEvent;
use AxiTrace\Model\Product;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Every event needs at least one identifier; a real web request supplies this via cookie.
$axiTrace->setClientId('visitor-' . uniqid());

// Example 1: Contact Form Submission
echo "Example 1: Contact Form Submission\n";
try {
    $response = $axiTrace->formSubmit('contact-form', [
        'email' => 'john.doe@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'phone' => '+1234567890',
        'message' => 'I am interested in your services.',
        'company' => 'Acme Corp',
    ]);
    echo "  - Contact form tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 2: Newsletter Subscription
echo "\nExample 2: Newsletter Subscription\n";
try {
    $response = $axiTrace->subscribe('subscriber@example.com', [
        'subscription_type' => 'newsletter',
    ]);
    echo "  - Newsletter subscription tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 3: Free Trial Start
echo "\nExample 3: Free Trial Start\n";
try {
    $response = $axiTrace->startTrial('Professional', [
        'trial_period_days' => 14,
        'trial_value' => 49.99,
        'trial_currency' => 'USD',
        'email' => 'trial@example.com',
    ]);
    echo "  - Free trial tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 4: Search Event
echo "\nExample 4: Search Event\n";
try {
    $response = $axiTrace->search('wireless headphones', [
        'results_count' => 42,
        'category' => 'Electronics',
        'filters' => [
            'brand' => 'Sony',
            'price_min' => 50,
            'price_max' => 200,
            'in_stock' => true,
        ],
        'sort_by' => 'price_asc',
        'page' => 1,
    ]);
    echo "  - Search event tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 5: Leads - products and value on lead events (1.12.0)
// Products are optional; an invalid line is dropped and the event is still sent.
// Lines are priced in the event value currency. unitCost is never sent on a lead.
echo "\nExample 5: Lead events with products and value\n";
try {
    // Quote request worth 1,200 EUR for one sofa (helper: products/value/currency/eventSalt in $params)
    $response = $axiTrace->formSubmit('quote-request', [
        'email' => 'jane.doe@example.com',
        'message' => 'Price for the grey three-seat sofa?',
    ], [
        'eventSalt' => 'quote-1001',
        'value' => 1200.00,
        'currency' => 'EUR',
        'products' => [
            ['sku' => 'SOFA-3', 'name' => 'Three-seat sofa', 'finalUnitPrice' => 1200.00, 'quantity' => 1],
        ],
    ]);
    echo "  - Quote request tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";

    // Paid box subscription (event model: setValue() + addProduct() with a Product instance)
    $subscribe = (new SubscribeEvent('reader@example.com'))
        ->setSubscriptionType('box')
        ->setEventSalt('subscription-2001')
        ->setValue(29.00, 'EUR')
        ->addProduct((new Product('BOX-M'))->setItemName('Monthly box')->setPrice(29.00)->setQuantity(1));
    $response = $axiTrace->track($subscribe);
    echo "  - Box subscription tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";

    // Trial of a plan: the trial currency anchors the product prices
    $response = $axiTrace->startTrial('Pro Plan', [
        'trial_value' => 49.99,
        'trial_currency' => 'USD',
        'eventSalt' => 'trial-3001',
        'products' => [
            ['sku' => 'PLAN-PRO', 'name' => 'Pro Plan', 'finalUnitPrice' => 49.99, 'quantity' => 1],
        ],
    ]);
    echo "  - Plan trial tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";

    // A registered custom event (must exist in the workspace's Custom Events registry)
    $custom = (new CustomEvent('quote_requested', ['menu_name' => 'Salad'], 149.50, 'PLN', null, 'quote-4001'))
        ->setProducts([
            ['sku' => 'MENU-SALAD', 'name' => 'Salad menu', 'finalUnitPrice' => 14.95, 'quantity' => 10],
        ]);
    $response = $axiTrace->events()->send($custom);
    echo "  - Custom lead event tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nForm tracking examples completed!\n";
