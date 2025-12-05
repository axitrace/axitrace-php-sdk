<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\ClientIdentity;

/**
 * Form submit event.
 *
 * Tracks form submissions (contact forms, lead generation, etc.).
 */
class FormSubmitEvent extends AbstractEvent
{
    /**
     * @var string
     */
    private string $label;

    /**
     * @var ClientIdentity
     */
    private ClientIdentity $client;

    /**
     * @var array<string, mixed>
     */
    private array $formParams = [];

    /**
     * @var string|null
     */
    private ?string $eventSalt = null;

    /**
     * @param string $label Form identifier (e.g., "contact-form", "newsletter-signup")
     */
    public function __construct(string $label)
    {
        $this->label = $label;
        $this->client = new ClientIdentity();
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/form/submit';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'form.submit';
    }

    /**
     * Set client identity.
     *
     * @param ClientIdentity $client
     * @return self
     */
    public function setClient(ClientIdentity $client): self
    {
        $this->client = $client;
        return $this;
    }

    /**
     * Set client custom ID (visitor ID).
     *
     * @param string $customId
     * @return self
     */
    public function setClientCustomId(string $customId): self
    {
        $this->client->setCustomId($customId);
        return $this;
    }

    /**
     * Set client email.
     *
     * @param string $email
     * @return self
     */
    public function setClientEmail(string $email): self
    {
        $this->client->setEmail($email);
        return $this;
    }

    /**
     * Set form email.
     *
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->formParams['email'] = $email;
        return $this;
    }

    /**
     * Set form parameters.
     *
     * @param array<string, mixed> $params
     * @return self
     */
    public function setFormParams(array $params): self
    {
        $this->formParams = array_merge($this->formParams, $params);
        return $this;
    }

    /**
     * Add a form parameter.
     *
     * @param string $key
     * @param mixed $value
     * @return self
     */
    public function addFormParam(string $key, $value): self
    {
        $this->formParams[$key] = $value;
        return $this;
    }

    /**
     * Set event salt for deduplication.
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
     * {@inheritdoc}
     */
    public function validate(): void
    {
        if (!$this->client->hasIdentifier()) {
            throw ValidationException::missingUserIdentifier();
        }

        if (empty($this->label)) {
            throw ValidationException::missingRequiredField('label', 'form.submit');
        }

        if (empty($this->formParams)) {
            throw ValidationException::missingRequiredField('params', 'form.submit');
        }

        // Validate email if present
        if (isset($this->formParams['email']) && !empty($this->formParams['email'])) {
            if (!filter_var($this->formParams['email'], FILTER_VALIDATE_EMAIL)) {
                throw ValidationException::invalidEmail($this->formParams['email']);
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = [
            'label' => $this->label,
            'client' => $this->client->toArray(),
            'params' => array_merge($this->formParams, $this->params),
        ];

        if ($this->sessionId !== null) {
            $data['sessionId'] = $this->sessionId;
        }

        if ($this->eventSalt !== null) {
            $data['eventSalt'] = $this->eventSalt;
        }

        return $data;
    }
}
