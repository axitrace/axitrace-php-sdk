<?php

/**
 * AxiTrace PHP SDK - Basic Usage Example
 *
 * This example demonstrates basic page view and product view tracking.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Model\Event\PageViewEvent;
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Product;

// Initialize the SDK with your secret key
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Example 1: Track a page view
try {
    $pageViewEvent = (new PageViewEvent())
        ->setClientId('visitor-123')
        ->setPageUrl('https://example.com/products')
        ->setPageTitle('Our Products')
        ->setReferrer('https://google.com');

    $response = $axiTrace->trackPageView($pageViewEvent);

    if ($response->isSuccess()) {
        echo "Page view tracked successfully! Event ID: " . $response->getEventId() . "\n";
    } else {
        echo "Error: " . $response->getError() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Example 2: Track a product view
try {
    $product = (new Product('SKU-001'))
        ->setItemName('Wireless Headphones')
        ->setPrice(79.99)
        ->setItemBrand('AudioTech')
        ->setItemCategory('Electronics')
        ->setItemCategory2('Audio')
        ->setQuantity(1);

    $productViewEvent = (new ProductViewEvent('USD', 79.99))
        ->setClientId('visitor-123')
        ->addItem($product);

    $response = $axiTrace->trackProductView($productViewEvent);

    if ($response->isSuccess()) {
        echo "Product view tracked successfully! Event ID: " . $response->getEventId() . "\n";
    } else {
        echo "Error: " . $response->getError() . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Example 3: Using automatic cookie detection (in web context)
// When running in a web context, the SDK can automatically read cookies
try {
    $pageViewEvent = (new PageViewEvent())
        ->setPageUrl('https://example.com/about')
        ->setPageTitle('About Us');

    // Auto-populate identifiers from cookies
    $axiTrace->populateFromCookies($pageViewEvent);

    // If cookies were found, the event will have client_id, session_id, etc.
    $response = $axiTrace->trackPageView($pageViewEvent);

    echo "Page view with cookies tracked! Event ID: " . $response->getEventId() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

// Example 4: Using Facebook parameters for conversion tracking
try {
    $pageViewEvent = (new PageViewEvent())
        ->setClientId('visitor-123')
        ->setPageUrl('https://example.com/landing')
        ->setPageTitle('Special Offer')
        ->setFbp('fb.1.1234567890.987654321')
        ->setFbc('fb.1.1234567890.AbCdEfGh');

    $response = $axiTrace->trackPageView($pageViewEvent);

    echo "Page view with Facebook params tracked! Event ID: " . $response->getEventId() . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
