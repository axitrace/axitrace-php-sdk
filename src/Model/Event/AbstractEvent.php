<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Abstract base class for all events.
 */
abstract class AbstractEvent implements EventInterface
{
    /**
     * @var string|null
     */
    protected ?string $clientId = null;

    /**
     * @var string|null
     */
    protected ?string $userId = null;

    /**
     * @var string|null
     */
    protected ?string $sessionId = null;

    /**
     * @var array<string, mixed>
     */
    protected array $params = [];

    /**
     * Set client ID (visitor ID from vt_vid cookie).
     *
     * @param string $clientId
     * @return static
     */
    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * Set user ID (authenticated user).
     *
     * @param string $userId
     * @return static
     */
    public function setUserId(string $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * Set session ID (from vt_sid cookie).
     *
     * @param string $sessionId
     * @return static
     */
    public function setSessionId(string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Set additional parameters.
     *
     * @param array<string, mixed> $params
     * @return static
     */
    public function setParams(array $params): self
    {
        $this->params = array_merge($this->params, $params);
        return $this;
    }

    /**
     * Add a single parameter.
     *
     * @param string $key
     * @param mixed $value
     * @return static
     */
    public function addParam(string $key, $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    /**
     * Set Facebook pixel ID (_fbp cookie).
     *
     * @param string $fbp
     * @return static
     */
    public function setFbp(string $fbp): self
    {
        $this->params['fbp'] = $fbp;
        return $this;
    }

    /**
     * Set Facebook click ID (_fbc cookie).
     *
     * @param string $fbc
     * @return static
     */
    public function setFbc(string $fbc): self
    {
        $this->params['fbc'] = $fbc;
        return $this;
    }

    /**
     * Set customer phone number.
     *
     * @param string $phone
     * @return static
     */
    public function setPhone(string $phone): self
    {
        $this->params['phone'] = $phone;
        return $this;
    }

    /**
     * Set customer first name.
     *
     * @param string $firstName
     * @return static
     */
    public function setFirstName(string $firstName): self
    {
        $this->params['first_name'] = $firstName;
        return $this;
    }

    /**
     * Set customer last name.
     *
     * @param string $lastName
     * @return static
     */
    public function setLastName(string $lastName): self
    {
        $this->params['last_name'] = $lastName;
        return $this;
    }

    /**
     * Set customer city.
     *
     * @param string $city
     * @return static
     */
    public function setCity(string $city): self
    {
        $this->params['city'] = $city;
        return $this;
    }

    /**
     * Set customer state.
     *
     * @param string $state
     * @return static
     */
    public function setState(string $state): self
    {
        $this->params['state'] = $state;
        return $this;
    }

    /**
     * Set customer ZIP code.
     *
     * @param string $zip
     * @return static
     */
    public function setZip(string $zip): self
    {
        $this->params['zip'] = $zip;
        return $this;
    }

    /**
     * Set customer country.
     *
     * @param string $country
     * @return static
     */
    public function setCountry(string $country): self
    {
        $this->params['country'] = $country;
        return $this;
    }

    /**
     * Get client ID.
     *
     * @return string|null
     */
    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    /**
     * Get user ID.
     *
     * @return string|null
     */
    public function getUserId(): ?string
    {
        return $this->userId;
    }

    /**
     * Get session ID.
     *
     * @return string|null
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get additional parameters.
     *
     * @return array<string, mixed>
     */
    public function getParams(): array
    {
        return $this->params;
    }

    /**
     * Check if event has at least one user identifier.
     *
     * @return bool
     */
    public function hasUserIdentifier(): bool
    {
        return $this->clientId !== null
            || $this->userId !== null
            || $this->sessionId !== null;
    }

    /**
     * Validate that at least one user identifier is set.
     *
     * @throws ValidationException
     */
    protected function validateUserIdentifier(): void
    {
        if (!$this->hasUserIdentifier()) {
            throw ValidationException::missingUserIdentifier();
        }
    }

    /**
     * Build base array with common fields.
     *
     * @return array<string, mixed>
     */
    protected function buildBaseArray(): array
    {
        $data = [];

        if ($this->clientId !== null) {
            $data['client_id'] = $this->clientId;
        }

        if ($this->userId !== null) {
            $data['user_id'] = $this->userId;
        }

        if ($this->sessionId !== null) {
            $data['session_id'] = $this->sessionId;
        }

        return $data;
    }

    /**
     * Add params to array if not empty.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function addParamsToArray(array $data): array
    {
        if (!empty($this->params)) {
            $data['params'] = $this->params;
        }

        return $data;
    }

    /**
     * Build client object for API request.
     *
     * @return array<string, mixed>
     */
    protected function buildClientObject(): array
    {
        $client = [];

        if ($this->clientId !== null) {
            $client['customId'] = $this->clientId;
        }

        if ($this->userId !== null) {
            $client['uuid'] = $this->userId;
        }

        return $client;
    }
}
