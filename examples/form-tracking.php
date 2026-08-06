<?php

/**
 * AxiTrace PHP SDK - Form Tracking Example
 *
 * Demonstrates form submission, newsletter subscription, trial start, and search
 * tracking using the AxiTrace facade methods.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;

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

echo "\nForm tracking examples completed!\n";
