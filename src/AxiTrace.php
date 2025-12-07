<?php

declare(strict_types=1);

namespace AxiTrace;

use AxiTrace\Api\EventsApi;
use AxiTrace\Api\Response;
use AxiTrace\Client\HttpClient;
use AxiTrace\Exception\ApiException;
use AxiTrace\Exception\AuthenticationException;
use AxiTrace\Exception\ConfigurationException;
use AxiTrace\Exception\ValidationException;
use AxiTrace\Helper\CookieHelper;
use AxiTrace\Model\ClientIdentity;
use AxiTrace\Model\Event\AddPaymentInfoEvent;
use AxiTrace\Model\Event\AddShippingInfoEvent;
use AxiTrace\Model\Event\AddToCartEvent;
use AxiTrace\Model\Event\BeginCheckoutEvent;
use AxiTrace\Model\Event\EventInterface;
use AxiTrace\Model\Event\FormSubmitEvent;
use AxiTrace\Model\Event\PageViewEvent;
use AxiTrace\Model\Event\ProductViewEvent;
use AxiTrace\Model\Event\RemoveFromCartEvent;
use AxiTrace\Model\Event\SearchEvent;
use AxiTrace\Model\Event\SelectItemEvent;
use AxiTrace\Model\Event\StartTrialEvent;
use AxiTrace\Model\Event\SubscribeEvent;
use AxiTrace\Model\Event\TransactionEvent;
use AxiTrace\Model\Event\ViewItemListEvent;
use AxiTrace\Model\Money;
use AxiTrace\Model\Product;
use GuzzleHttp\Client as GuzzleClient;

/**
 * Main AxiTrace SDK client.
 *
 * @example
 * // Initialize
 * $axitrace = AxiTrace::init('sk_live_your_secret_key');
 *
 * // Track page view
 * $axitrace->pageView('https://example.com/page');
 *
 * // Track transaction
 * $axitrace->transaction('ORDER-123', 99.99, 89.99, 'USD', 'CARD');
 */
class AxiTrace
{
    /**
     * @var Config
     */
    private Config $config;

    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    /**
     * @var EventsApi
     */
    private EventsApi $eventsApi;

    /**
     * @var string|null
     */
    private ?string $clientId = null;

    /**
     * @var string|null
     */
    private ?string $userId = null;

    /**
     * @var string|null
     */
    private ?string $sessionId = null;

    /**
     * @var bool
     */
    private bool $autoReadCookies;

    /**
     * @param Config $config
     * @param GuzzleClient|null $guzzleClient
     */
    public function __construct(Config $config, ?GuzzleClient $guzzleClient = null)
    {
        $this->config = $config;
        $this->httpClient = new HttpClient($config, $guzzleClient);
        $this->eventsApi = new EventsApi($this->httpClient);
        $this->autoReadCookies = true;
    }

    /**
     * Initialize AxiTrace with a secret key.
     *
     * @param string $secretKey
     * @param array<string, mixed> $options
     * @return self
     * @throws ConfigurationException
     */
    public static function init(string $secretKey, array $options = []): self
    {
        $config = new Config($secretKey, $options);
        return new self($config);
    }

    /**
     * Initialize from environment variables.
     *
     * @param array<string, mixed> $options
     * @return self
     * @throws ConfigurationException
     */
    public static function fromEnvironment(array $options = []): self
    {
        $config = Config::fromEnvironment($options);
        return new self($config);
    }

    /**
     * Set client ID (visitor ID).
     *
     * @param string $clientId
     * @return self
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * Set user ID.
     *
     * @param string $userId
     * @return self
     */
    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set session ID.
     *
     * @param string $sessionId
     * @return self
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Disable automatic cookie reading.
     *
     * @return self
     */
    public function disableAutoReadCookies(): self
    {
        $this->autoReadCookies = false;
        return $this;
    }

    /**
     * Enable automatic cookie reading.
     *
     * @return self
     */
    public function enableAutoReadCookies(): self
    {
        $this->autoReadCookies = true;
        return $this;
    }

    /**
     * Get visitor ID from cookie.
     *
     * @return string|null
     */
    public function getVisitorId(): ?string
    {
        return $this->clientId ?? CookieHelper::getVisitorId();
    }

    /**
     * Get session ID from cookie.
     *
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId ?? CookieHelper::getSessionId();
    }

    /**
     * Get user ID from cookie.
     *
     * @return string|null
     */
    public function getUserIdFromCookie(): ?string
    {
        return CookieHelper::getUserId();
    }

    /**
     * Track a generic event.
     *
     * @param EventInterface $event
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function track(EventInterface $event): Response
    {
        $this->applyIdentifiers($event);
        return $this->eventsApi->send($event);
    }

    /**
     * Track page view.
     *
     * @param string $url
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function pageView(string $url, array $params = []): Response
    {
        $event = new PageViewEvent($url);

        if (isset($params['title'])) {
            $event->setTitle($params['title']);
            unset($params['title']);
        }

        if (isset($params['referrer'])) {
            $event->setReferrer($params['referrer']);
            unset($params['referrer']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track product view.
     *
     * @param Product|array<string, mixed> $product
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function productView($product, array $params = []): Response
    {
        if (is_array($product)) {
            $product = Product::fromArray($product);
        }

        $event = new ProductViewEvent($product);

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track add to cart.
     *
     * @param float $value
     * @param string $currency
     * @param array<array<string, mixed>|Product> $items
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function addToCart(float $value, string $currency, array $items, array $params = []): Response
    {
        $event = new AddToCartEvent($currency, $value);
        $event->setItems($items);

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track remove from cart.
     *
     * @param float $value
     * @param string $currency
     * @param array<array<string, mixed>|Product> $items
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function removeFromCart(float $value, string $currency, array $items, array $params = []): Response
    {
        $event = new RemoveFromCartEvent($currency, $value);
        $event->setItems($items);

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track begin checkout.
     *
     * @param float $value
     * @param string $currency
     * @param array<array<string, mixed>|Product> $items
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function beginCheckout(float $value, string $currency, array $items, array $params = []): Response
    {
        $event = new BeginCheckoutEvent($currency, $value);
        $event->setItems($items);

        if (isset($params['coupon'])) {
            $event->setCoupon($params['coupon']);
            unset($params['coupon']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track add payment info.
     *
     * @param float $value
     * @param string $currency
     * @param array<array<string, mixed>|Product> $items
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function addPaymentInfo(float $value, string $currency, array $items, array $params = []): Response
    {
        $event = new AddPaymentInfoEvent($currency, $value);
        $event->setItems($items);

        if (isset($params['payment_type'])) {
            $event->setPaymentType($params['payment_type']);
            unset($params['payment_type']);
        }

        if (isset($params['coupon'])) {
            $event->setCoupon($params['coupon']);
            unset($params['coupon']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track add shipping info.
     *
     * @param float $value
     * @param string $currency
     * @param array<array<string, mixed>|Product> $items
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function addShippingInfo(float $value, string $currency, array $items, array $params = []): Response
    {
        $event = new AddShippingInfoEvent($currency, $value);
        $event->setItems($items);

        if (isset($params['shipping_tier'])) {
            $event->setShippingTier($params['shipping_tier']);
            unset($params['shipping_tier']);
        }

        if (isset($params['coupon'])) {
            $event->setCoupon($params['coupon']);
            unset($params['coupon']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track transaction (purchase).
     *
     * @param string $orderId
     * @param float $revenue
     * @param float $value
     * @param string $currency
     * @param string $paymentMethod
     * @param array<array<string, mixed>> $products
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function transaction(
        string $orderId,
        float $revenue,
        float $value,
        string $currency,
        string $paymentMethod,
        array $products,
        array $params = []
    ): Response {
        $source = $params['source'] ?? TransactionEvent::SOURCE_WEB_DESKTOP;
        unset($params['source']);

        $event = TransactionEvent::create(
            $orderId,
            $revenue,
            $value,
            $currency,
            $paymentMethod,
            $source
        );

        $event->setProducts($products);

        // Set client identity from cookies (CRITICAL: This links PHP SDK events to JS SDK profile)
        $clientId = $this->getVisitorId();
        if ($clientId !== null) {
            $event->setClientCustomId($clientId);
        }

        // Set session ID from cookie (CRITICAL: This enables profile matching across SDKs)
        $sessionId = $this->getSessionId();
        if ($sessionId !== null) {
            $event->setSessionId($sessionId);
        }

        if (isset($params['email'])) {
            $event->setClientEmail($params['email']);
            unset($params['email']);
        }

        if (isset($params['discount_amount']) && isset($params['discount_currency'])) {
            $event->setDiscountAmount(new Money($params['discount_amount'], $params['discount_currency']));
            unset($params['discount_amount'], $params['discount_currency']);
        }

        if (isset($params['metadata'])) {
            $event->setMetadata($params['metadata']);
            unset($params['metadata']);
        }

        // Apply Facebook cookies for CAPI matching
        if ($this->autoReadCookies) {
            $fbp = CookieHelper::getFbp();
            if ($fbp !== null) {
                $event->setFbp($fbp);
            }

            $fbc = CookieHelper::getFbc();
            if ($fbc !== null) {
                $event->setFbc($fbc);
            }
        }

        return $this->eventsApi->send($event);
    }

    /**
     * Track subscription.
     *
     * @param string $email
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function subscribe(string $email, array $params = []): Response
    {
        $event = new SubscribeEvent($email);

        if (isset($params['subscription_type'])) {
            $event->setSubscriptionType($params['subscription_type']);
            unset($params['subscription_type']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track start trial.
     *
     * @param string $planName
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function startTrial(string $planName, array $params = []): Response
    {
        $event = new StartTrialEvent($planName);

        if (isset($params['trial_period_days'])) {
            $event->setTrialPeriodDays((int) $params['trial_period_days']);
            unset($params['trial_period_days']);
        }

        if (isset($params['trial_value']) && isset($params['trial_currency'])) {
            $event->setTrialValue((float) $params['trial_value'], $params['trial_currency']);
            unset($params['trial_value'], $params['trial_currency']);
        }

        if (isset($params['predicted_ltv'])) {
            $event->setPredictedLtv((float) $params['predicted_ltv']);
            unset($params['predicted_ltv']);
        }

        if (isset($params['email'])) {
            $event->setEmail($params['email']);
            unset($params['email']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track search.
     *
     * @param string $searchTerm
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function search(string $searchTerm, array $params = []): Response
    {
        $event = new SearchEvent($searchTerm);

        if (isset($params['results_count'])) {
            $event->setResultsCount((int) $params['results_count']);
            unset($params['results_count']);
        }

        if (isset($params['category'])) {
            $event->setCategory($params['category']);
            unset($params['category']);
        }

        if (isset($params['filters'])) {
            $event->setFilters($params['filters']);
            unset($params['filters']);
        }

        if (isset($params['sort_by'])) {
            $event->setSortBy($params['sort_by']);
            unset($params['sort_by']);
        }

        if (isset($params['page'])) {
            $event->setPage((int) $params['page']);
            unset($params['page']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track form submit.
     *
     * @param string $label
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function formSubmit(string $label, array $formData, array $params = []): Response
    {
        $event = new FormSubmitEvent($label);
        $event->setFormParams($formData);

        // Set client identity
        $clientId = $this->getVisitorId();
        if ($clientId !== null) {
            $event->setClientCustomId($clientId);
        }

        if (isset($formData['email'])) {
            $event->setClientEmail($formData['email']);
        }

        $sessionId = $this->getSessionId();
        if ($sessionId !== null) {
            $event->setSessionId($sessionId);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->eventsApi->send($event);
    }

    /**
     * Track view item list.
     *
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function viewItemList(array $params = []): Response
    {
        $event = new ViewItemListEvent();

        if (isset($params['item_list_id'])) {
            $event->setItemListId($params['item_list_id']);
            unset($params['item_list_id']);
        }

        if (isset($params['item_list_name'])) {
            $event->setItemListName($params['item_list_name']);
            unset($params['item_list_name']);
        }

        if (isset($params['items'])) {
            $event->setItems($params['items']);
            unset($params['items']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Track select item.
     *
     * @param Product|array<string, mixed> $item
     * @param array<string, mixed> $params
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function selectItem($item, array $params = []): Response
    {
        if (is_array($item)) {
            $item = Product::fromArray($item);
        }

        $event = new SelectItemEvent($item);

        if (isset($params['item_list_id'])) {
            $event->setItemListId($params['item_list_id']);
            unset($params['item_list_id']);
        }

        if (isset($params['item_list_name'])) {
            $event->setItemListName($params['item_list_name']);
            unset($params['item_list_name']);
        }

        if (!empty($params)) {
            $event->setParams($params);
        }

        return $this->track($event);
    }

    /**
     * Get the Events API.
     *
     * @return EventsApi
     */
    public function events(): EventsApi
    {
        return $this->eventsApi;
    }

    /**
     * Get the configuration.
     *
     * @return Config
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Apply identifiers to an event.
     *
     * @param EventInterface $event
     */
    private function applyIdentifiers(EventInterface $event): void
    {
        // Only apply if event supports these methods (AbstractEvent)
        if (!method_exists($event, 'setClientId')) {
            return;
        }

        // Apply client ID if not already set
        if ($this->clientId !== null && $event->getClientId() === null) {
            $event->setClientId($this->clientId);
        } elseif ($this->autoReadCookies && $event->getClientId() === null) {
            $cookieClientId = CookieHelper::getVisitorId();
            if ($cookieClientId !== null) {
                $event->setClientId($cookieClientId);
            }
        }

        // Apply user ID if not already set
        if ($this->userId !== null && $event->getUserId() === null) {
            $event->setUserId($this->userId);
        }

        // Apply session ID if not already set
        if ($this->sessionId !== null && $event->getSessionId() === null) {
            $event->setSessionId($this->sessionId);
        } elseif ($this->autoReadCookies && $event->getSessionId() === null) {
            $cookieSessionId = CookieHelper::getSessionId();
            if ($cookieSessionId !== null) {
                $event->setSessionId($cookieSessionId);
            }
        }

        // Apply Facebook cookies if auto-read is enabled
        if ($this->autoReadCookies) {
            $fbp = CookieHelper::getFbp();
            if ($fbp !== null) {
                $event->setFbp($fbp);
            }

            $fbc = CookieHelper::getFbc();
            if ($fbc !== null) {
                $event->setFbc($fbc);
            }
        }
    }
}
