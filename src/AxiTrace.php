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
     * Client IP address to forward to the API.
     * This should be the end-user's IP address, not the server's IP.
     *
     * @var string|null
     */
    private ?string $clientIp = null;

    /**
     * Client user agent to forward to the API.
     * This should be the end-user's browser user agent, not the SDK's.
     *
     * @var string|null
     */
    private ?string $clientUserAgent = null;

    /**
     * Page URL to use for events without URL (e.g., transactions).
     *
     * @var string|null
     */
    private ?string $pageUrl = null;

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

        // Auto-detect client IP and user agent from PHP superglobals if available
        $this->autoDetectClientData();
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
     * Set client IP address to forward to the API.
     * IMPORTANT: For server-side events (like transactions), this should be
     * the end-user's IP address, not your server's IP.
     *
     * @param string $clientIp
     * @return self
     */
    public function setClientIp(string $clientIp): self
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    /**
     * Get the client IP address that will be forwarded.
     *
     * @return string|null
     */
    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    /**
     * Set client user agent to forward to the API.
     * IMPORTANT: For server-side events (like transactions), this should be
     * the end-user's browser user agent, not a server-side default.
     *
     * @param string $clientUserAgent
     * @return self
     */
    public function setClientUserAgent(string $clientUserAgent): self
    {
        $this->clientUserAgent = $clientUserAgent;
        return $this;
    }

    /**
     * Get the client user agent that will be forwarded.
     *
     * @return string|null
     */
    public function getClientUserAgent(): ?string
    {
        return $this->clientUserAgent;
    }

    /**
     * Set the page URL for events without URL (e.g., server-side transactions).
     * IMPORTANT: This URL should match a domain verified in your Facebook pixel settings.
     *
     * @param string $pageUrl
     * @return self
     * @throws \InvalidArgumentException If the URL is not valid
     */
    public function setPageUrl(string $pageUrl): self
    {
        // Validate URL to prevent malformed data being sent to Facebook CAPI
        if (!filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            throw new \InvalidArgumentException('Invalid page URL: ' . $pageUrl);
        }
        $this->pageUrl = $pageUrl;
        return $this;
    }

    /**
     * Get the page URL.
     *
     * @return string|null
     */
    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    /**
     * Auto-detect client IP and user agent from PHP superglobals.
     * This is called automatically on construction but can be disabled.
     *
     * @return self
     */
    public function autoDetectClientData(): self
    {
        // Auto-detect IP from various headers (in order of priority)
        if ($this->clientIp === null) {
            $ipHeaders = [
                'HTTP_CF_CONNECTING_IP',     // Cloudflare
                'HTTP_X_FORWARDED_FOR',      // Standard proxy header
                'HTTP_X_REAL_IP',            // Nginx
                'HTTP_CLIENT_IP',            // Some proxies
                'HTTP_X_CLIENT_IP',          // Some proxies
                'REMOTE_ADDR',               // Direct connection
            ];

            foreach ($ipHeaders as $header) {
                if (!empty($_SERVER[$header])) {
                    // For X-Forwarded-For, take the first IP (client IP)
                    $ip = $_SERVER[$header];
                    if (strpos($ip, ',') !== false) {
                        $ips = array_map('trim', explode(',', $ip));
                        $ip = $ips[0]; // First IP is the client
                    }
                    if ($this->isValidIp($ip)) {
                        $this->clientIp = $ip;
                        break;
                    }
                }
            }
        }

        // Auto-detect user agent
        if ($this->clientUserAgent === null && !empty($_SERVER['HTTP_USER_AGENT'])) {
            $this->clientUserAgent = $_SERVER['HTTP_USER_AGENT'];
        }

        // Auto-detect page URL from referer or current URL
        if ($this->pageUrl === null) {
            // Try HTTP_REFERER first (for API calls from a web page)
            // SECURITY: Validate URL to prevent malformed data being sent to Facebook CAPI
            if (!empty($_SERVER['HTTP_REFERER']) && filter_var($_SERVER['HTTP_REFERER'], FILTER_VALIDATE_URL)) {
                $this->pageUrl = $_SERVER['HTTP_REFERER'];
            }
            // Try to construct from REQUEST_URI
            elseif (!empty($_SERVER['HTTP_HOST']) && !empty($_SERVER['REQUEST_URI'])) {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $constructedUrl = $scheme . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                // Validate the constructed URL
                if (filter_var($constructedUrl, FILTER_VALIDATE_URL)) {
                    $this->pageUrl = $constructedUrl;
                }
            }
        }

        return $this;
    }

    /**
     * Disable auto-detection of client data.
     * Call this if you want to manually set client IP/user agent.
     *
     * @return self
     */
    public function clearClientData(): self
    {
        $this->clientIp = null;
        $this->clientUserAgent = null;
        $this->pageUrl = null;
        return $this;
    }

    /**
     * Check if an IP address is valid.
     *
     * @param string $ip
     * @return bool
     */
    private function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
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
        $this->syncClientDataToHttpClient();

        // Set page URL on event if not already set and we have one
        if ($this->pageUrl !== null && method_exists($event, 'setUrl') && method_exists($event, 'getUrl')) {
            if (empty($event->getUrl())) {
                $event->setUrl($this->pageUrl);
            }
        }

        return $this->eventsApi->send($event);
    }

    /**
     * Sync client data (IP, user agent) to the HTTP client.
     * This ensures the ingestion API receives the real client data.
     *
     * @return void
     */
    private function syncClientDataToHttpClient(): void
    {
        $this->httpClient->setClientIp($this->clientIp);
        $this->httpClient->setClientUserAgent($this->clientUserAgent);
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

        // Apply remaining params (attribution data like fbclid, utm_source, fbp, fbc, etc.)
        // CRITICAL: This enables server-side attribution when JS SDK PageView fails
        if (!empty($params)) {
            $event->setParams($params);
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

        // Apply page URL for event_source_url in Facebook CAPI
        if ($this->pageUrl !== null && method_exists($event, 'setUrl')) {
            $event->setUrl($this->pageUrl);
        }

        // Sync client data and send event
        $this->syncClientDataToHttpClient();

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
