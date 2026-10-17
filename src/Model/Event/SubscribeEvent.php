<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Subscribe event.
 *
 * Tracks newsletter/mailing list subscriptions.
 */
class SubscribeEvent extends AbstractEvent
{
    use LeadProductsTrait;
    use LeadMessageTrait;

    /**
     * @var string
     */
    private string $email;

    /**
     * @var string|null
     */
    private ?string $subscriptionType = null;

    /**
     * @var string|null
     */
    private ?string $eventSalt = null;

    /**
     * @var float|null
     */
    private ?float $value = null;

    /**
     * @var string|null
     */
    private ?string $currency = null;

    /**
     * @param string $email
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/subscribe';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'subscribe';
    }

    /**
     * Set subscription type.
     *
     * @param string $subscriptionType
     * @return self
     */
    public function setSubscriptionType(string $subscriptionType): self
    {
        $this->subscriptionType = $subscriptionType;
        return $this;
    }

    /**
     * Set event salt for deduplication: a retried submission with the same salt is stored once.
     *
     * @param string $eventSalt
     * @return self
     */
    public function setEventSalt(string $eventSalt): self
    {
        $this->eventSalt = $eventSalt;
        return $this;
    }

    /**
     * Set the value of the subscription, sent as root value and currency.
     *
     * Context for reports, never counted as revenue.
     *
     * @param float $value
     * @param string $currency ISO 4217 code
     * @return self
     */
    public function setValue(float $value, string $currency): self
    {
        $this->value = $value;
        $this->currency = strtoupper($currency);
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->email)) {
            throw ValidationException::missingRequiredField('email', 'subscribe');
        }

        if (!filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::invalidEmail($this->email);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = $this->buildBaseArray();

        $data['email'] = $this->email;

        if ($this->subscriptionType !== null) {
            $data['subscription_type'] = $this->subscriptionType;
        }

        if ($this->eventSalt !== null) {
            $data['eventSalt'] = $this->eventSalt;
        }

        $products = $this->buildLeadProducts($this->currency);
        if ($products !== []) {
            $data['products'] = $products;
        }

        if ($this->value !== null) {
            $data['value'] = $this->value;
        }

        if ($this->currency !== null) {
            $data['currency'] = $this->currency;
        }

        return $this->addParamsToArray($this->withLeadMessage($data));
    }
}
