<?php

/**
 * AxiTrace PHP SDK - Form Tracking Example
 *
 * This example demonstrates form submission and subscription tracking.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Model\Event\FormSubmitEvent;
use AxiTrace\Model\Event\SubscribeEvent;
use AxiTrace\Model\Event\StartTrialEvent;
use AxiTrace\Model\Event\SearchEvent;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Simulated visitor ID
$visitorId = 'visitor-' . uniqid();

// Example 1: Contact Form Submission
echo "Example 1: Contact Form Submission\n";
try {
    $formSubmitEvent = (new FormSubmitEvent('contact-form'))
        ->setClientCustomId($visitorId)
        ->setClientEmail('john.doe@example.com')
        ->setSessionId('session-' . uniqid())
        ->setFormParams([
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '+1234567890',
            'message' => 'I am interested in your services.',
            'company' => 'Acme Corp',
        ])
        ->setEventSalt('contact-' . time()); // Prevent duplicate submissions

    $response = $axiTrace->trackFormSubmit($formSubmitEvent);
    echo "  - Contact form tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 2: Newsletter Subscription
echo "\nExample 2: Newsletter Subscription\n";
try {
    $subscribeEvent = (new SubscribeEvent())
        ->setClientId($visitorId)
        ->setEmail('subscriber@example.com')
        ->setSubscriptionType('newsletter')
        ->setSubscriptionSource('homepage_popup');

    $response = $axiTrace->trackSubscribe($subscribeEvent);
    echo "  - Newsletter subscription tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 3: Free Trial Start
echo "\nExample 3: Free Trial Start\n";
try {
    $startTrialEvent = (new StartTrialEvent())
        ->setClientId($visitorId)
        ->setUserId('user-12345')
        ->setEmail('trial@example.com')
        ->setPlanName('Professional')
        ->setTrialDays(14)
        ->setTrialValue(49.99);

    $response = $axiTrace->trackStartTrial($startTrialEvent);
    echo "  - Free trial tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 4: Search Event
echo "\nExample 4: Search Event\n";
try {
    $searchEvent = (new SearchEvent('wireless headphones'))
        ->setClientId($visitorId)
        ->setResultsCount(42)
        ->setCategory('Electronics')
        ->setFilters([
            'brand' => 'Sony',
            'price_min' => 50,
            'price_max' => 200,
            'in_stock' => true,
        ])
        ->setSortBy('price_asc')
        ->setPage(1);

    $response = $axiTrace->trackSearch($searchEvent);
    echo "  - Search event tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 5: Lead Generation Form (with custom form type)
echo "\nExample 5: Lead Generation Form\n";
try {
    $leadFormEvent = (new FormSubmitEvent('lead-generation'))
        ->setClientCustomId($visitorId)
        ->setClientEmail('lead@company.com')
        ->setFormParams([
            'email' => 'lead@company.com',
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'company' => 'Tech Startup Inc',
            'job_title' => 'CTO',
            'phone' => '+1987654321',
            'employees' => '50-100',
            'interest' => 'Enterprise Plan',
            'referral_source' => 'LinkedIn Ad',
        ]);

    $response = $axiTrace->trackFormSubmit($leadFormEvent);
    echo "  - Lead form tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nForm tracking examples completed!\n";
