<?php

declare(strict_types=1);

namespace AxiTrace\Model;

/**
 * Client identity model for form.submit and transaction events.
 */
class ClientIdentity
{
    /**
     * @var string|null
     */
    private ?string $customId = null;

    /**
     * @var int|null
     */
    private ?int $id = null;

    /**
     * @var string|null
     */
    private ?string $uuid = null;

    /**
     * @var string|null
     */
    private ?string $email = null;

    /**
     * Customer phone number (E.164 recommended, e.g. +14155552671).
     * Read by the ingestion API for CAPI/CRM matching, unlike the generic
     * params.phone value set via AbstractEvent::setPhone().
     *
     * @var string|null
     */
    private ?string $phone = null;

    /**
     * Buyer's given name. Forwarded to Meta CAPI `fn` and TikTok `first_name`
     * (both hash it downstream — pass plain text).
     *
     * @var string|null
     */
    private ?string $firstName = null;

    /**
     * Buyer's family name. Forwarded to Meta CAPI `ln` / TikTok `last_name`.
     *
     * @var string|null
     */
    private ?string $lastName = null;

    /**
     * Buyer's city. Forwarded to Meta CAPI `ct` / TikTok `city`.
     *
     * @var string|null
     */
    private ?string $city = null;

    /**
     * Buyer's state, province or region. Forwarded to Meta CAPI `st` / TikTok `state`.
     *
     * @var string|null
     */
    private ?string $state = null;

    /**
     * Buyer's postal code. Forwarded to Meta CAPI `zp` / TikTok `zip_code`.
     *
     * @var string|null
     */
    private ?string $zip = null;

    /**
     * Buyer's country, ISO 3166-1 alpha-2 preferred (e.g. "CH").
     * Forwarded to Meta CAPI `country` / TikTok `country`.
     *
     * @var string|null
     */
    private ?string $country = null;

    /**
     * Create from array.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $client = new self();

        if (isset($data['customId'])) {
            $client->setCustomId($data['customId']);
        }

        if (isset($data['id'])) {
            $client->setId((int) $data['id']);
        }

        if (isset($data['uuid'])) {
            $client->setUuid($data['uuid']);
        }

        if (isset($data['email'])) {
            $client->setEmail($data['email']);
        }

        if (isset($data['phone'])) {
            $client->setPhone($data['phone']);
        }

        if (isset($data['firstName'])) {
            $client->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $client->setLastName($data['lastName']);
        }

        if (isset($data['city'])) {
            $client->setCity($data['city']);
        }

        if (isset($data['state'])) {
            $client->setState($data['state']);
        }

        if (isset($data['zip'])) {
            $client->setZip($data['zip']);
        }

        if (isset($data['country'])) {
            $client->setCountry($data['country']);
        }

        return $client;
    }

    /**
     * Convert to array for API.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->customId !== null) {
            $data['customId'] = $this->customId;
        }

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->uuid !== null) {
            $data['uuid'] = $this->uuid;
        }

        if ($this->email !== null) {
            $data['email'] = $this->email;
        }

        if ($this->phone !== null) {
            $data['phone'] = $this->phone;
        }

        if ($this->firstName !== null) {
            $data['firstName'] = $this->firstName;
        }

        if ($this->lastName !== null) {
            $data['lastName'] = $this->lastName;
        }

        if ($this->city !== null) {
            $data['city'] = $this->city;
        }

        if ($this->state !== null) {
            $data['state'] = $this->state;
        }

        if ($this->zip !== null) {
            $data['zip'] = $this->zip;
        }

        if ($this->country !== null) {
            $data['country'] = $this->country;
        }

        return $data;
    }

    /**
     * Check if client has at least one identifier.
     *
     * @return bool
     */
    public function hasIdentifier(): bool
    {
        return $this->customId !== null
            || $this->id !== null
            || $this->uuid !== null
            || $this->email !== null
            || $this->phone !== null;
    }

    // Fluent setters

    /**
     * @param string $customId
     * @return self
     */
    public function setCustomId(string $customId): self
    {
        $this->customId = $customId;
        return $this;
    }

    /**
     * @param int $id
     * @return self
     */
    public function setId(int $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @param string $uuid
     * @return self
     */
    public function setUuid(string $uuid): self
    {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @param string $email
     * @return self
     */
    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @param string $phone
     * @return self
     */
    public function setPhone(string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    // Getters

    /**
     * @return string|null
     */
    public function getCustomId(): ?string
    {
        return $this->customId;
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return string|null
     */
    public function getUuid(): ?string
    {
        return $this->uuid;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return string|null
     */
    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @param string $firstName
     * @return self
     */
    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @param string $lastName
     * @return self
     */
    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * @param string $city
     * @return self
     */
    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    /**
     * @param string $state
     * @return self
     */
    public function setState(string $state): self
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @param string $zip
     * @return self
     */
    public function setZip(string $zip): self
    {
        $this->zip = $zip;
        return $this;
    }

    /**
     * @param string $country
     * @return self
     */
    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @return string|null
     */
    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    /**
     * @return string|null
     */
    public function getCity(): ?string
    {
        return $this->city;
    }

    /**
     * @return string|null
     */
    public function getState(): ?string
    {
        return $this->state;
    }

    /**
     * @return string|null
     */
    public function getZip(): ?string
    {
        return $this->zip;
    }

    /**
     * @return string|null
     */
    public function getCountry(): ?string
    {
        return $this->country;
    }
}
