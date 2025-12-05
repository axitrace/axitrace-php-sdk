<?php

declare(strict_types=1);

namespace AxiTrace\Model;

/**
 * Client identity model for form.submit events.
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
            || $this->email !== null;
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
}
