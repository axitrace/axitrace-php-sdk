# AxiTrace PHP SDK

Official PHP SDK for the AxiTrace event tracking API. Track page views, e-commerce events, form submissions, and more.

## Requirements

- PHP 7.4 or higher
- ext-json
- ext-curl
- Guzzle HTTP client 7.0+

## Installation

Install via Composer:

```bash
composer require axitrace/axitrace-php-sdk
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Model\Event\PageViewEvent;

// Initialize with your secret key
$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Track a page view
$event = (new PageViewEvent())
    ->setClientId('visitor-123')
    ->setPageUrl('https://example.com/page')
    ->setPageTitle('My Page');

$response = $axiTrace->trackPageView($event);

if ($response->isSuccess()) {
    echo "Event tracked! ID: " . $response->getEventId();
}
```

## Configuration

### Basic Configuration

```php
use AxiTrace\AxiTrace;

$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');
```

### Advanced Configuration

```php
use AxiTrace\Config;
use AxiTrace\AxiTrace;

$config = new Config('sk_live_your_secret_key_here');
$config->setBaseUrl('https://custom-endpoint.axitrace.com');
$config->setTimeout(60);
$config->setVerifySsl(true);

$axiTrace = new AxiTrace($config);
```

## Authentication

The SDK uses API key authentication. Your secret key should start with:
- `sk_live_` for production
- `sk_test_` for testing

Keep your secret key secure and never expose it in client-side code.

## Events

### Page View

```php
use AxiTrace\Model\Event\PageViewEvent;

$event = (new PageViewEvent())
    ->setClientId('visitor-123')
    ->setPageUrl('https://example.com/page')
    ->setPageTitle('Page Title')
    ->setReferrer('https://google.com');

$axiTrace->trackPageView($event);
```

### Product View

```php
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setItemBrand('Brand')
    ->setItemCategory('Category');

$event = (new ProductViewEvent('USD', 99.99))
    ->setClientId('visitor-123')
    ->addItem($product);

$axiTrace->trackProductView($event);
```

### Add to Cart

```php
use AxiTrace\Model\Event\AddToCartEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setQuantity(2);

$event = (new AddToCartEvent('USD', 199.98))
    ->setClientId('visitor-123')
    ->addItem($product);

$axiTrace->trackAddToCart($event);
```

### Remove from Cart

```php
use AxiTrace\Model\Event\RemoveFromCartEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setQuantity(1);

$event = (new RemoveFromCartEvent('USD', 99.99))
    ->setClientId('visitor-123')
    ->addItem($product);

$axiTrace->trackRemoveFromCart($event);
```

### Begin Checkout

```php
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Product;

$event = (new BeginCheckoutEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(199.99))
    ->addItem((new Product('SKU-002'))->setItemName('Product 2')->setPrice(100.00))
    ->setCoupon('SAVE10');

$axiTrace->trackBeginCheckout($event);
```

### Add Shipping Info

```php
use AxiTrace\Model\Event\AddShippingInfoEvent;

$event = (new AddShippingInfoEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(299.99))
    ->setShippingTier('express')
    ->setCoupon('SAVE10');

$axiTrace->trackAddShippingInfo($event);
```

### Add Payment Info

```php
use AxiTrace\Model\Event\AddPaymentInfoEvent;

$event = (new AddPaymentInfoEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(299.99))
    ->setPaymentType('credit_card')
    ->setCoupon('SAVE10');

$axiTrace->trackAddPaymentInfo($event);
```

### Transaction (Purchase)

```php
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Product;

$event = (new TransactionEvent('ORDER-123', 'USD', 324.99))
    ->setClientId('visitor-123')
    ->setUserId('user-456')
    ->setEmail('customer@example.com')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(299.99)->setQuantity(1))
    ->setTax(25.00)
    ->setShipping(10.00)
    ->setCoupon('SAVE10')
    ->setAffiliation('Main Store');

$axiTrace->trackTransaction($event);
```

### Subscribe

```php
use AxiTrace\Model\Event\SubscribeEvent;

$event = (new SubscribeEvent())
    ->setClientId('visitor-123')
    ->setEmail('subscriber@example.com')
    ->setSubscriptionType('newsletter')
    ->setSubscriptionSource('footer_form');

$axiTrace->trackSubscribe($event);
```

### Start Trial

```php
use AxiTrace\Model\Event\StartTrialEvent;

$event = (new StartTrialEvent())
    ->setClientId('visitor-123')
    ->setUserId('user-456')
    ->setEmail('trial@example.com')
    ->setPlanName('Pro Plan')
    ->setTrialDays(14)
    ->setTrialValue(49.99);

$axiTrace->trackStartTrial($event);
```

### Search

```php
use AxiTrace\Model\Event\SearchEvent;

$event = (new SearchEvent('wireless headphones'))
    ->setClientId('visitor-123')
    ->setResultsCount(42)
    ->setCategory('Electronics')
    ->setFilters(['brand' => 'Sony', 'price_max' => 200])
    ->setSortBy('relevance')
    ->setPage(1);

$axiTrace->trackSearch($event);
```

### Form Submit

```php
use AxiTrace\Model\Event\FormSubmitEvent;

$event = (new FormSubmitEvent('contact-form'))
    ->setClientCustomId('visitor-123')
    ->setClientEmail('user@example.com')
    ->setFormParams([
        'email' => 'user@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'message' => 'Hello!',
    ]);

$axiTrace->trackFormSubmit($event);
```

### View Item List

```php
use AxiTrace\Model\Event\ViewItemListEvent;
use AxiTrace\Model\Product;

$event = (new ViewItemListEvent())
    ->setClientId('visitor-123')
    ->setItemListId('category-electronics')
    ->setItemListName('Electronics')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(99.99)->setIndex(0))
    ->addItem((new Product('SKU-002'))->setItemName('Product 2')->setPrice(149.99)->setIndex(1));

$axiTrace->trackViewItemList($event);
```

### Select Item

```php
use AxiTrace\Model\Event\SelectItemEvent;
use AxiTrace\Model\Product;

$event = (new SelectItemEvent())
    ->setClientId('visitor-123')
    ->setItemListId('category-electronics')
    ->setItemListName('Electronics')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(99.99)->setIndex(0));

$axiTrace->trackSelectItem($event);
```

## User Identification

The SDK supports multiple user identification methods:

```php
// Anonymous visitor (cookie-based)
$event->setClientId('vt_vid_cookie_value');

// Session-based
$event->setSessionId('session-id');

// Logged-in user
$event->setUserId('user-database-id');

// Email (for transactions)
$event->setEmail('user@example.com');
```

### Automatic Cookie Detection

In a web context, you can automatically populate identifiers from cookies:

```php
$event = new PageViewEvent();
$axiTrace->populateFromCookies($event);
```

This reads the following cookies:
- `vt_vid` - Visitor ID
- `vt_sid` - Session ID
- `vt_uid` - User ID
- `_fbp` - Facebook browser ID
- `_fbc` - Facebook click ID

## Facebook Conversion API Integration

Track events for Facebook CAPI:

```php
$event = (new TransactionEvent('ORDER-123', 'USD', 99.99))
    ->setClientId('visitor-123')
    ->setFbp('fb.1.1234567890.987654321')
    ->setFbc('fb.1.1234567890.AbCdEfGh')
    ->addItem(new Product('SKU-001'));
```

## Error Handling

```php
use AxiTrace\Exception\ValidationException;
use AxiTrace\Exception\AuthenticationException;
use AxiTrace\Exception\ApiException;

try {
    $response = $axiTrace->trackPageView($event);

    if (!$response->isSuccess()) {
        echo "API Error: " . $response->getError();
    }
} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage();
} catch (AuthenticationException $e) {
    echo "Auth error: " . $e->getMessage();
} catch (ApiException $e) {
    echo "API error: " . $e->getMessage();
}
```

## Product Model

The Product model supports all GA4-compatible fields:

```php
$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setQuantity(1)
    ->setItemBrand('Brand Name')
    ->setItemCategory('Category')
    ->setItemCategory2('Subcategory')
    ->setItemCategory3('Sub-subcategory')
    ->setItemCategory4('Deep category')
    ->setItemCategory5('Deepest category')
    ->setItemVariant('Blue / Large')
    ->setItemListId('list-123')
    ->setItemListName('Search Results')
    ->setIndex(0)
    ->setCoupon('DISCOUNT10')
    ->setDiscount(10.00)
    ->setAffiliation('Partner Store')
    ->setLocationId('LOC-001');
```

### Creating Products from Arrays

```php
$product = Product::fromArray([
    'item_id' => 'SKU-001',
    'item_name' => 'Product Name',
    'price' => 99.99,
    'quantity' => 1,
]);
```

## Examples

See the `examples/` directory for complete usage examples:

- `basic-usage.php` - Page views and product views
- `ecommerce-tracking.php` - Full e-commerce flow
- `form-tracking.php` - Forms and subscriptions
- `catalog-tracking.php` - Item lists and selections

## Testing

Run the test suite:

```bash
composer install
vendor/bin/phpunit
```

## License

MIT License. See LICENSE file for details.
