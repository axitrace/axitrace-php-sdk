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

### Custom Event

Merchant-defined custom events go through a DEDICATED endpoint
(`POST /v1/custom-events`) and require PRE-REGISTRATION: the event key must
be defined in the workspace's Custom Events registry (AxiTrace admin panel,
workspace Settings, Advanced) BEFORE it can be sent. An unknown name is
rejected by the ingestion with HTTP 400 and the stable message prefix
`unknown custom event: <name>`.

The endpoint is the ONLY /v1 endpoint with event_id deduplication: sending
the same event_id twice is answered `202 duplicate`, so the event is safe
to retry. Pass your own `event_id` (any non-empty string, at most 100
characters) to make retries idempotent.

The key pattern is `[a-z][a-z0-9_]{0,39}` (lowercase letters, numbers and
underscores, starting with a letter, GA4-compatible). Note that the
`once_per_page_view` throttle behaves as unlimited for server callers; the
server applies only `once_per_session` and `every_n_seconds`.

```php
use AxiTrace\Model\Event\CustomEvent;

$event = new CustomEvent(
    'quote_requested',            // the pre-registered key
    ['menu_name' => 'Salad'],     // declared properties
    149.50,                       // optional value (conversion kind only)
    'PLN',                        // optional currency
    'order-1001',                 // optional transaction id
    'dedup-id-1'                  // caller-supplied event_id (safe to retry)
);

$axiTrace->events()->send($event);
```

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

$orderId = 'ORDER-123';

$event = TransactionEvent::create(
    $orderId,
    299.99,                // Revenue
    279.99,                // Value (after discounts)
    'USD',                 // Currency
    'CARD',                // Payment method
    TransactionEvent::SOURCE_WEB_DESKTOP
);

$event->setClientCustomId('visitor-123')
      ->setClientEmail('customer@example.com')
      ->setClientPhone('+14155552671')
      // eventSalt is the ONLY dedup guard for this endpoint - see "Products and eventSalt"
      // below. Using the orderId is the recommended value.
      ->setEventSalt($orderId)
      ->setProducts([
          [
              'sku' => 'SKU-001',
              'name' => 'Product 1',
              // A bare number is fine - see "Products and eventSalt" below for the full shape rules.
              'finalUnitPrice' => 279.99,
              'quantity' => 1,
          ],
      ])
      ->setDiscountAmount(new Money(20.00, 'USD'));

$axiTrace->events()->send($event);
```

Or, using the `AxiTrace` facade (recommended - it also applies visitor identifiers and
attribution data automatically):

```php
$axiTrace->transaction(
    $orderId,
    299.99,   // revenue
    279.99,   // value
    'USD',
    'CARD',
    [
        ['sku' => 'SKU-001', 'name' => 'Product 1', 'finalUnitPrice' => 279.99, 'quantity' => 1],
    ],
    [
        'email' => 'customer@example.com',
        'event_salt' => $orderId, // or 'eventSalt' - both keys are accepted
    ]
);
```

#### Buyer match keys (Event Match Quality)

If your shop holds the buyer's billing or shipping details, pass them - this is the single
largest available improvement to Purchase Event Match Quality:

```php
$axiTrace->transaction($orderId, 299.99, 279.99, 'USD', 'CARD', $products, [
    'email'      => 'customer@example.com',
    'phone'      => '+41791234567',
    'first_name' => 'Ada',
    'last_name'  => 'Lovelace',
    'city'       => 'Zurich',
    'state'      => 'ZH',      // state, province or region
    'zip'        => '8001',
    'country'    => 'CH',      // ISO 3166-1 alpha-2 preferred
    'event_salt' => $orderId,
]);
```

Pass **plain text** - hashing happens server-side (Meta CAPI `fn`/`ln`/`ct`/`st`/`zp`/
`country`, TikTok `first_name`/`last_name`/`city`/`state`/`zip_code`/`country`). camelCase
spellings are accepted too (`firstName`, `lastName`, `postalCode`, `zipCode`, `province`).
Empty and whitespace-only values are dropped rather than sent as blank match keys.

Requires SDK 1.5.0 or newer; older versions silently ignored everything except `email`.

#### Products and eventSalt

Two things caused the most support time for real integrations, so the SDK now guards
against both locally, before any HTTP call is made:

1. **`finalUnitPrice` shape.** Write it as a bare number - `'finalUnitPrice' => 279.99` -
   the SDK normalizes it into `{amount: 279.99, currency: <transaction currency>}` for you.
   An already-shaped `['amount' => 279.99, 'currency' => 'USD']` also works if a line item
   needs a different currency. Anything that cannot be coerced to a number throws a
   `ValidationException` immediately, with the field path, what was received, and a
   correct example - instead of an opaque `400 Invalid JSON` from the API.
2. **`quantity` as a numeric string.** `'quantity' => '2'` (common when the value comes
   from `$_POST` or a CSV import) is cast to an int automatically.

You can also pass `AxiTrace\Model\Product` instances instead of arrays - `sku` falls back
to the item ID, `name`, `finalUnitPrice` and `quantity` are read from the model:

```php
use AxiTrace\Model\Product;

$event->setProducts([
    (new Product('SKU-001'))->setItemName('Product 1')->setPrice(279.99)->setQuantity(1),
]);
```

**`eventSalt`** is the deduplication guard for `/v1/transaction` - unlike the browser
`/track` endpoint, this endpoint performs **no server-side dedup**. If a call can ever be
retried (a payment webhook firing twice, a job retrying after a timeout), set `eventSalt`
to something stable across retries - the order ID is the recommended value. Omitting it
behaves exactly as before (no `eventSalt` sent).

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

## Ad click ids and browser ids

Inside a web request the SDK reads the ad identifiers itself when it is created, and sends
them in the `params` of every event:

| Source | Identifiers |
|--------|-------------|
| URL of the current request | `fbclid`, `gclid`, `gbraid`, `wbraid`, `ttclid`, `msclkid`, `twclid`, `epik`, `li_fat_id`, `ScCid` or `sccid` (SDK 1.11.0+ reads Snapchat's `ScCid`), `rdt_cid`, `oppref`, `utm_*`, `campaign_id`, `adset_id`, `ad_id` |
| Click-id cookies set by the AxiTrace JavaScript SDK (SDK 1.10.0+) | `_gclid`, `_gbraid`, `_wbraid`, `_ttclid` (90 days), `_rdt_cid`, `_oppref` (28 days) |
| Click-id cookies set by the AxiTrace JavaScript SDK 0.24.0+ (SDK 1.11.0+) | `_axi_msclkid`, `_axi_twclid` (90 days), `_axi_epik` (60 days), `_axi_li_fat_id` (30 days), `_axi_sccid` (28 days) |
| Ad platforms' own click-id cookies, last fallback (SDK 1.11.0+) | `_uetmsclkid` (Microsoft UET), `_twclid` (X), `_epik` (Pinterest), `li_fat_id` (LinkedIn) |
| Ad platform pixel cookies | `_fbp`, `_fbc`, `_ttp`, `_ga`, `_rdt_uuid` and `__obref` (SDK 1.10.0+) |

The JavaScript SDK keeps a click id from the landing page URL in a first-party cookie, so a
conversion sent from a later request (a form POST, a checkout step) still carries the click
that brought the visitor. A click id in the current URL wins over the cookie, and a click
older than the window above is not sent. The platforms' own cookies are read only when
neither the URL nor the AxiTrace cookie has the click id, and never written. A value you pass yourself, in the event params or
through `setAttributionParams()`, wins over both.

## Sending events outside a web request (queue, cron, webhook)

`transaction()` and every other `AxiTrace` facade method resolve the visitor via
`getVisitorId()`/`getSessionId()`, which fall back to reading the `vt_vid`/`vt_sid`
cookies through `CookieHelper`. **Outside a normal web request - a queue worker, a cron
job, a payment webhook handler run from the CLI - `$_COOKIE` is empty.** The event is
still accepted by the API (`HTTP 200`), but it ships with no client identifier, no
session ID, and your server's IP/user agent instead of the visitor's. Nothing errors -
the conversion is just silently unattributed and never linked back to the visitor's
session.

The fix is `withContext()`: capture the visitor's `vt_vid`/`vt_sid` cookies, IP, and user
agent at the point the visitor is actually on your site (e.g. store them on the
order/payment record at checkout), then replay them before sending the event from the
queue/cron job:

```php
use AxiTrace\AxiTrace;

$axiTrace = AxiTrace::init('sk_live_your_secret_key_here');

// Values captured during the original HTTP request and persisted on the order,
// not read from $_COOKIE/$_SERVER here - there is no request to read them from.
$axiTrace->withContext([
    'clientId' => $order->getVisitorId(),   // vt_vid cookie value, captured at checkout
    'sessionId' => $order->getSessionId(),  // vt_sid cookie value, captured at checkout
    'ip' => $order->getCustomerIp(),
    'userAgent' => $order->getCustomerUserAgent(),
])->transaction(
    $order->getId(),
    $order->getRevenue(),
    $order->getValue(),
    $order->getCurrency(),
    $order->getPaymentMethod(),
    $order->getProductsForTracking(),
    [
        'event_salt' => $order->getId(),          // webhooks retry - always set eventSalt here
        'recorded_at' => $order->getCreatedAt(),  // when the order was placed, not when this job runs
    ]
);
```

The ad click ids and browser ids (see "Ad click ids and browser ids" above) are not there
either. Store `$axiTrace->getAttributionParams()` on the order during the visitor's request
and pass it to `setAttributionParams()` in the job, before sending the event.

### Dating a late transaction: `recorded_at`

A transaction sent later than the order was placed - from a queue that was backed up, a cron job
that re-sends failed deliveries, a backfill - is dated at the moment it reaches AxiTrace unless
you say otherwise, so it lands on the wrong day in reports and reaches the ad platforms as a
fresh conversion. Pass the time the order was placed (SDK 1.8.0+):

```php
// Facade: a DateTimeInterface or an ISO 8601 string
$axiTrace->transaction($orderId, 299.99, 279.99, 'USD', 'CARD', $products, [
    'event_salt' => $orderId,
    'recorded_at' => new DateTimeImmutable('2026-09-17 14:05:07', new DateTimeZone('Europe/Warsaw')),
]);

// Event model
$event->setRecordedAt($order->getCreatedAt());
```

- It is sent as `recordedAt` in UTC with millisecond precision; a time in the future is replaced
  by the server's clock. An unreadable string throws `ValidationException` before any request.
- Meta accepts conversions up to 7 days old. An older order is still recorded in AxiTrace and
  forwarded to platforms that accept older conversions (Google Ads: 90 days), but not to Meta.
- Keep `event_salt` set to your order id: a re-sent order that already arrived within the last
  30 days is then ignored instead of counted twice.

`withContext()` accepts (all optional): `clientId` (or `customId` as an alias),
`sessionId`, `ip`, `userAgent`, `consent`. It applies the corresponding `set*()` calls
and returns `$this` for chaining. See `examples/queue-transaction.php` for a full
runnable example.

## Profit tracking: cost fields and refunds

When profit tracking is enabled for your workspace, AxiTrace computes the profit of every order
and reports POAS (profit on ad spend) next to ROAS. Send what each order cost you and AxiTrace
uses it instead of the cost rules set in the admin panel (SDK 1.9.0+). Every field is optional:
a transaction without them is sent exactly as before.

```php
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Money;
use AxiTrace\Model\Product;

$event = TransactionEvent::create($orderId, 299.99, 279.99, 'EUR', 'CARD');
$event->setClientCustomId($axiTrace->getVisitorId() ?? $order->getCustomerId())
      ->setEventSalt($orderId)
      ->setTax(52.31)              // total tax of the order
      ->setShipping(9.99)          // shipping the buyer paid, tax included
      ->setTaxesIncluded(true)     // revenue and prices include tax
      // What the order cost you: shipping, payment fee, handling. A number is read in the
      // revenue currency; pass null for a cost you do not know and the workspace rule applies.
      ->setCosts(6.40, new Money(4.12, 'EUR'), null)
      ->setProducts([
          [
              'sku' => 'SKU-001',
              'name' => 'Office chair',
              'finalUnitPrice' => 149.99,
              'quantity' => 2,
              'unitCost' => 71.50,                 // what one unit cost you
              'externalId' => 'woocommerce:1234',  // the product id in your store platform
          ],
      ]);

// Or with the Product model
$event->addProduct(
    (new Product('ITEM-2'))->setSku('SKU-002')->setItemName('Desk mat')
        ->setPrice(19.99)->setQuantity(1)->setUnitCost(6.20)->setExternalId('woocommerce:88')
);

$axiTrace->track($event);
```

- Costs must be in the revenue currency and at least 0; anything else throws
  `ValidationException` before any request is made.
- Costs are accepted only with your secret key, which this SDK always uses. Never send them from
  a browser: AxiTrace drops cost fields that arrive without the secret key.
- `unitCost` and `externalId` travel only with a transaction. `Product::toArray()`, used by cart
  and catalog events, never includes them.

### Refunds and cancellations

Send a refund or a cancellation of an order you sent before. It reduces that order's profit and
POAS; revenue and ROAS reports do not change.

```php
$axiTrace->refund(
    $orderId,                // the orderId the transaction was sent with
    $creditMemo->getId(),    // your refund id: a retry with the same id is recorded once
    59.98,                   // total refunded to the buyer
    'EUR',                   // currency of the original transaction
    [
        // Refunded lines: identify each product by sku, externalId or both
        ['sku' => 'SKU-001', 'externalId' => 'woocommerce:1234', 'quantity' => 1, 'amount' => 49.99],
        ['sku' => 'SKU-002', 'quantity' => 1, 'amount' => 9.99],
    ],
    [
        'refunded_at' => $creditMemo->getCreatedAt(),  // DateTimeInterface or ISO 8601; default now
        'is_cancellation' => false,                    // true when the whole order was cancelled
    ]
);
```

Leave the lines out for a refund that returns no products, such as a goodwill or shipping refund.
The same request can be built with `AxiTrace\Model\Event\RefundEvent` (`addLine()`,
`setCancellation()`, `setRefundedAt()`) and sent with `$axiTrace->track($event)`. Refunds go to
`POST /v1/refund`, which accepts only the secret key.

## Cookie consent

If your site asks visitors for cookie consent, tell AxiTrace what they decided. Pass the
state through `withContext()` and it travels as `params.consent` on every event you send
afterwards:

```php
$axiTrace->withContext([
    'clientId' => $order->getVisitorId(),
    'consent' => $order->hasMarketingConsent() ? 'granted' : 'denied',
])->transaction(/* ... */);
```

Accepted values, and nothing else:

| Value | Meaning | What AxiTrace does |
|-------|---------|--------------------|
| `granted` | The visitor accepted marketing cookies. | The event is recorded and forwarded to your ad platforms. |
| `denied` | The visitor refused marketing cookies. | The event is recorded in your AxiTrace reports, but it is stripped of ad identifiers and is never forwarded to an ad platform. |
| `unknown` | You cannot tell (no banner, or the decision is not available here). | The event is recorded. Whether it is forwarded depends on the "Forward events that do not report a consent state" setting of your workspace. |

Any other value throws `AxiTrace\Exception\ValidationException`:

```php
use AxiTrace\Exception\ValidationException;

try {
    $axiTrace->withContext(['consent' => 'accepted']); // not a valid state
} catch (ValidationException $e) {
    // Invalid consent state "accepted". Use one of: granted, denied, unknown.
}
```

Omitting the `consent` key sends no `params.consent` at all, which the server reads as
an unknown state. Consent is remembered on the SDK instance, so set it before the first
event of the request and every later event carries it.

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
$event->setFirstName('John')
      ->setLastName('Doe')
      ->setCity('New York')
      ->setState('NY')
      ->setZip('10001')
      ->setCountry('US');
```

### Phone number

`phone` is one of the client identifiers the ingestion API reads for CAPI/CRM matching -
but it must be set on the **client identity**, not the generic params bag. For
`TransactionEvent` and `FormSubmitEvent` (the events that carry a client identity), use
`setClientPhone()`:

```php
$event->setClientPhone('+14155552671'); // E.164 recommended
```

`AbstractEvent::setPhone()` still exists and still works exactly as before (it writes
`params.phone`), but it is **deprecated** for this purpose - the API does not read
`params.phone` for identity matching, only `client.phone`. It remains available for events
that don't carry a client identity and for backward compatibility.

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
- `ecommerce-tracking.php` - Full e-commerce flow, including a transaction with `eventSalt` and correctly-shaped products
- `form-tracking.php` - Forms, subscriptions, trials, and search
- `catalog-tracking.php` - Item lists, selecting an item, and removing from cart
- `queue-transaction.php` - Sending a transaction from a queue/cron job with `withContext()`

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

- Documentation: https://axitrace.com/docs
- Issues: https://github.com/axitrace/axitrace-php-sdk/issues
