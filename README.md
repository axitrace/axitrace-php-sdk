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
composer require axitrace/php-sdk
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use AxiTrace\AxiTrace;
use AxiTrace\Config;
use AxiTrace\Model\Event\PageViewEvent;

// Initialize with your secret key
$config = new Config('sk_live_your_secret_key_here');
$axiTrace = new AxiTrace($config);

// Track a page view
$event = (new PageViewEvent('https://example.com/page'))
    ->setClientId('visitor-123')
    ->setTitle('My Page');

$response = $axiTrace->events()->send($event);

if ($response->isSuccess()) {
    echo "Event tracked! ID: " . $response->getEventId();
}
```

## Configuration

### Basic Configuration

```php
use AxiTrace\Config;
use AxiTrace\AxiTrace;

$config = new Config('sk_live_your_secret_key_here');
$axiTrace = new AxiTrace($config);
```

### Advanced Configuration

```php
use AxiTrace\Config;
use AxiTrace\AxiTrace;

$config = new Config('sk_live_your_secret_key_here', [
    'base_url' => 'https://stat.axitrace.com',
    'timeout' => 60,
    'verify_ssl' => true,
    'debug' => false,
]);

$axiTrace = new AxiTrace($config);
```

### Configuration from Environment

```php
// Set AXITRACE_SECRET_KEY environment variable
$config = Config::fromEnvironment();
$axiTrace = new AxiTrace($config);
```

## Authentication

The SDK uses API key authentication. Your secret key should start with:
- `sk_live_` for production
- `sk_test_` for testing

Keep your secret key secure and never expose it in client-side code.

## Events

All events are sent using `$axiTrace->events()->send($event)`.

### Page View

```php
use AxiTrace\Model\Event\PageViewEvent;

$event = (new PageViewEvent('https://example.com/page'))
    ->setClientId('visitor-123')
    ->setSessionId('session-456')
    ->setTitle('Page Title')
    ->setReferrer('https://google.com');

$axiTrace->events()->send($event);
```

### Product View

```php
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setCurrency('USD')
    ->setItemBrand('Brand')
    ->setItemCategory('Category');

$event = ProductViewEvent::fromArray([
    'item_id' => 'SKU-001',
    'item_name' => 'Product Name',
    'price' => 99.99,
]);
$event->setClientId('visitor-123');

$axiTrace->events()->send($event);
```

### Add to Cart

```php
use AxiTrace\Model\Event\AddToCartEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setCurrency('USD')
    ->setQuantity(2);

$event = (new AddToCartEvent('USD', 199.98))
    ->setClientId('visitor-123')
    ->addItem($product);

$axiTrace->events()->send($event);
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

$axiTrace->events()->send($event);
```

### Begin Checkout

```php
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Product;

$event = (new BeginCheckoutEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(199.99)->setQuantity(1))
    ->addItem((new Product('SKU-002'))->setItemName('Product 2')->setPrice(100.00)->setQuantity(1))
    ->setCoupon('SAVE10');

$axiTrace->events()->send($event);
```

### Add Shipping Info

```php
use AxiTrace\Model\Event\AddShippingInfoEvent;
use AxiTrace\Model\Product;

$event = (new AddShippingInfoEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(299.99)->setQuantity(1))
    ->setShippingTier('express')
    ->setCoupon('SAVE10');

$axiTrace->events()->send($event);
```

### Add Payment Info

```php
use AxiTrace\Model\Event\AddPaymentInfoEvent;
use AxiTrace\Model\Product;

$event = (new AddPaymentInfoEvent('USD', 299.99))
    ->setClientId('visitor-123')
    ->addItem((new Product('SKU-001'))->setItemName('Product 1')->setPrice(299.99)->setQuantity(1))
    ->setPaymentType('credit_card')
    ->setCoupon('SAVE10');

$axiTrace->events()->send($event);
```

### Transaction (Purchase)

```php
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Money;

$event = TransactionEvent::create(
    'ORDER-123',           // Order ID
    299.99,                // Revenue
    279.99,                // Value (after discounts)
    'USD',                 // Currency
    'CARD',                // Payment method
    TransactionEvent::SOURCE_WEB_DESKTOP
);

$event->setClientCustomId('visitor-123')
      ->setClientEmail('customer@example.com')
      ->setProducts([
          [
              'sku' => 'SKU-001',
              'name' => 'Product 1',
              'finalUnitPrice' => ['amount' => 299.99, 'currency' => 'USD'],
              'quantity' => 1,
          ],
      ])
      ->setDiscountAmount(new Money(20.00, 'USD'));

$axiTrace->events()->send($event);
```

### Subscribe

```php
use AxiTrace\Model\Event\SubscribeEvent;

$event = (new SubscribeEvent('subscriber@example.com'))
    ->setClientId('visitor-123')
    ->setSubscriptionType('newsletter');

$axiTrace->events()->send($event);
```

### Start Trial

```php
use AxiTrace\Model\Event\StartTrialEvent;

$event = (new StartTrialEvent('Pro Plan'))
    ->setClientId('visitor-123')
    ->setTrialPeriodDays(14)
    ->setTrialValue(49.99, 'USD');

$axiTrace->events()->send($event);
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

$axiTrace->events()->send($event);
```

### Form Submit

```php
use AxiTrace\Model\Event\FormSubmitEvent;

$event = (new FormSubmitEvent('contact-form'))
    ->setClientCustomId('visitor-123')
    ->setClientEmail('user@example.com')
    ->setEmail('user@example.com')
    ->setFormParams([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'message' => 'Hello!',
    ]);

$axiTrace->events()->send($event);
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

$axiTrace->events()->send($event);
```

### Select Item

```php
use AxiTrace\Model\Event\SelectItemEvent;
use AxiTrace\Model\Product;

$product = (new Product('SKU-001'))
    ->setItemName('Product 1')
    ->setPrice(99.99)
    ->setIndex(0);

$event = (new SelectItemEvent($product))
    ->setClientId('visitor-123')
    ->setItemListId('category-electronics')
    ->setItemListName('Electronics');

$axiTrace->events()->send($event);
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
```

At least one identifier is required for all events.

## Facebook Conversion API Integration

Track events for Facebook CAPI:

```php
$event = (new PageViewEvent('https://example.com'))
    ->setClientId('visitor-123')
    ->setFbp('fb.1.1234567890.987654321')
    ->setFbc('fb.1.1234567890.AbCdEfGh');

$axiTrace->events()->send($event);
```

## Customer Data

Add customer data for enhanced tracking:

```php
$event->setPhone('+1234567890')
      ->setFirstName('John')
      ->setLastName('Doe')
      ->setCity('New York')
      ->setState('NY')
      ->setZip('10001')
      ->setCountry('US');
```

## Error Handling

```php
use AxiTrace\Exception\ValidationException;
use AxiTrace\Exception\AuthenticationException;
use AxiTrace\Exception\ApiException;

try {
    $response = $axiTrace->events()->send($event);

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

The Product model supports all standard e-commerce fields:

```php
$product = (new Product('SKU-001'))
    ->setItemName('Product Name')
    ->setPrice(99.99)
    ->setQuantity(1)
    ->setCurrency('USD')
    ->setItemBrand('Brand Name')
    ->setItemCategory('Category')
    ->setItemVariant('Blue / Large')
    ->setIndex(0)
    ->setUrl('https://example.com/product')
    ->setImage('https://example.com/image.jpg')
    ->setInStock(true)
    ->setStockQuantity(50);
```

### Creating Products from Arrays

```php
$product = Product::fromArray([
    'item_id' => 'SKU-001',
    'item_name' => 'Product Name',
    'price' => 99.99,
    'quantity' => 1,
    'currency' => 'USD',
]);
```

## Batch Sending

Send multiple events at once:

```php
$events = [
    (new PageViewEvent('https://example.com/page1'))->setClientId('visitor-123'),
    (new PageViewEvent('https://example.com/page2'))->setClientId('visitor-123'),
];

$responses = $axiTrace->events()->sendBatch($events);
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

Run integration tests (requires API key):

```bash
AXITRACE_SECRET_KEY=sk_live_xxx php bin/sdk-integration-test.php
```

## License

MIT License. See LICENSE file for details.

## Support

- Documentation: https://stat.axitrace.com/docs
- Issues: https://github.com/axitrace/axitrace-php-sdk/issues
