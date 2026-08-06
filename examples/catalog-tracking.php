<?php

/**
 * AxiTrace PHP SDK - Catalog Tracking Example
 *
 * Demonstrates product catalog tracking: viewing item lists, selecting an item, and
 * removing an item from the cart - using the AxiTrace facade methods.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Exception\AxiTraceException;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Every event needs at least one identifier; a real web request supplies this via cookie.
$axiTrace->setClientId('visitor-' . uniqid());

// Example 1: View Item List (Category Page)
echo "Example 1: View Item List (Category Page)\n";
try {
    $response = $axiTrace->viewItemList([
        'item_list_id' => 'category-electronics',
        'item_list_name' => 'Electronics',
        'items' => [
            ['item_id' => 'SKU-001', 'item_name' => 'Wireless Headphones', 'price' => 79.99, 'item_brand' => 'AudioTech', 'index' => 0],
            ['item_id' => 'SKU-002', 'item_name' => 'Bluetooth Speaker', 'price' => 49.99, 'item_brand' => 'SoundMax', 'index' => 1],
            ['item_id' => 'SKU-003', 'item_name' => 'USB-C Cable', 'price' => 12.99, 'item_brand' => 'TechCable', 'index' => 2],
        ],
    ]);
    echo "  - Category view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 2: View Item List (Search Results)
echo "\nExample 2: View Item List (Search Results)\n";
try {
    $response = $axiTrace->viewItemList([
        'item_list_id' => 'search-headphones',
        'item_list_name' => 'Search: headphones',
        'items' => [
            ['item_id' => 'SKU-SEARCH-001', 'item_name' => 'Pro Headphones', 'price' => 149.99, 'index' => 0],
            ['item_id' => 'SKU-SEARCH-002', 'item_name' => 'Budget Headphones', 'price' => 29.99, 'index' => 1],
        ],
    ]);
    echo "  - Search results view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 3: Select Item (Click on a product in a list)
echo "\nExample 3: Select Item\n";
try {
    $response = $axiTrace->selectItem(
        [
            'item_id' => 'SKU-001',
            'item_name' => 'Wireless Headphones',
            'price' => 79.99,
            'item_brand' => 'AudioTech',
            'index' => 0,
        ],
        [
            'item_list_id' => 'category-electronics',
            'item_list_name' => 'Electronics',
        ]
    );
    echo "  - Item selection tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 4: Remove from Cart
echo "\nExample 4: Remove from Cart\n";
try {
    $response = $axiTrace->removeFromCart(49.99, 'USD', [
        ['item_id' => 'SKU-002', 'item_name' => 'Bluetooth Speaker', 'price' => 49.99, 'quantity' => 1],
    ]);
    echo "  - Remove from cart tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED: " . $response->getError()) . "\n";
} catch (AxiTraceException $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nCatalog tracking examples completed!\n";
