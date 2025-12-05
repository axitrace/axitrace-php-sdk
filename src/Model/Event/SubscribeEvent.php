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
    /**
     * @var string
     */
    private string $email;

    /**
     * @var string|null
     */
    private ?string $subscriptionType = null;

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

        return $this->addParamsToArray($data);
    }
}
