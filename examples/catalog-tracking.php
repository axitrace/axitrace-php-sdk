<?php

/**
 * AxiTrace PHP SDK - Catalog Tracking Example
 *
 * This example demonstrates product catalog tracking: viewing item lists and selecting items.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Model\Event\ViewItemListEvent;
use AxiTrace\Model\Event\SelectItemEvent;
use AxiTrace\Model\Event\RemoveFromCartEvent;
use AxiTrace\Model\Product;

// Initialize the SDK
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Simulated visitor ID
$visitorId = 'visitor-' . uniqid();

// Example 1: View Item List (Category Page)
echo "Example 1: View Item List (Category Page)\n";
try {
    $products = [
        (new Product('SKU-001'))
            ->setItemName('Wireless Headphones')
            ->setPrice(79.99)
            ->setItemBrand('AudioTech')
            ->setItemCategory('Electronics')
            ->setIndex(0),
        (new Product('SKU-002'))
            ->setItemName('Bluetooth Speaker')
            ->setPrice(49.99)
            ->setItemBrand('SoundMax')
            ->setItemCategory('Electronics')
            ->setIndex(1),
        (new Product('SKU-003'))
            ->setItemName('USB-C Cable')
            ->setPrice(12.99)
            ->setItemBrand('TechCable')
            ->setItemCategory('Electronics')
            ->setIndex(2),
        (new Product('SKU-004'))
            ->setItemName('Phone Stand')
            ->setPrice(19.99)
            ->setItemBrand('DeskPro')
            ->setItemCategory('Electronics')
            ->setIndex(3),
    ];

    $viewItemListEvent = (new ViewItemListEvent())
        ->setClientId($visitorId)
        ->setItemListId('category-electronics')
        ->setItemListName('Electronics');

    foreach ($products as $product) {
        $viewItemListEvent->addItem($product);
    }

    $response = $axiTrace->trackViewItemList($viewItemListEvent);
    echo "  - Category view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 2: View Item List (Search Results)
echo "\nExample 2: View Item List (Search Results)\n";
try {
    $searchResults = [
        (new Product('SKU-SEARCH-001'))
            ->setItemName('Pro Headphones')
            ->setPrice(149.99)
            ->setItemBrand('AudioPro')
            ->setIndex(0),
        (new Product('SKU-SEARCH-002'))
            ->setItemName('Budget Headphones')
            ->setPrice(29.99)
            ->setItemBrand('ValueAudio')
            ->setIndex(1),
    ];

    $viewSearchResultsEvent = (new ViewItemListEvent())
        ->setClientId($visitorId)
        ->setItemListId('search-headphones')
        ->setItemListName('Search: headphones');

    foreach ($searchResults as $product) {
        $viewSearchResultsEvent->addItem($product);
    }

    $response = $axiTrace->trackViewItemList($viewSearchResultsEvent);
    echo "  - Search results view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 3: View Item List (Recommendations)
echo "\nExample 3: View Item List (Recommendations)\n";
try {
    $recommendations = [
        (new Product('SKU-REC-001'))
            ->setItemName('Recommended Product 1')
            ->setPrice(39.99)
            ->setIndex(0),
        (new Product('SKU-REC-002'))
            ->setItemName('Recommended Product 2')
            ->setPrice(59.99)
            ->setIndex(1),
    ];

    $viewRecommendationsEvent = (new ViewItemListEvent())
        ->setClientId($visitorId)
        ->setItemListId('recommendations-homepage')
        ->setItemListName('Recommended For You');

    foreach ($recommendations as $product) {
        $viewRecommendationsEvent->addItem($product);
    }

    $response = $axiTrace->trackViewItemList($viewRecommendationsEvent);
    echo "  - Recommendations view tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 4: Select Item (Click on product in list)
echo "\nExample 4: Select Item\n";
try {
    $selectedProduct = (new Product('SKU-001'))
        ->setItemName('Wireless Headphones')
        ->setPrice(79.99)
        ->setItemBrand('AudioTech')
        ->setItemCategory('Electronics')
        ->setIndex(0);

    $selectItemEvent = (new SelectItemEvent())
        ->setClientId($visitorId)
        ->setItemListId('category-electronics')
        ->setItemListName('Electronics')
        ->addItem($selectedProduct);

    $response = $axiTrace->trackSelectItem($selectItemEvent);
    echo "  - Item selection tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

// Example 5: Remove from Cart
echo "\nExample 5: Remove from Cart\n";
try {
    $removedProduct = (new Product('SKU-002'))
        ->setItemName('Bluetooth Speaker')
        ->setPrice(49.99)
        ->setQuantity(1);

    $removeFromCartEvent = (new RemoveFromCartEvent('USD', 49.99))
        ->setClientId($visitorId)
        ->addItem($removedProduct);

    $response = $axiTrace->trackRemoveFromCart($removeFromCartEvent);
    echo "  - Remove from cart tracked: " . ($response->isSuccess() ? "SUCCESS" : "FAILED") . "\n";
} catch (\Exception $e) {
    echo "  - Error: " . $e->getMessage() . "\n";
}

echo "\nCatalog tracking examples completed!\n";
