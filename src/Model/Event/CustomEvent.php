<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * A merchant-defined custom event (Task 15, T3).
 *
 * Server callers send through the dedicated endpoint POST /v1/custom-events,
 * which is the ONLY /v1 endpoint with event_id deduplication - the same
 * event_id sent twice is answered 202 duplicate, so the event is safe to
 * retry.
 *
 * Pre-registration: the event key must be defined in the workspace's Custom
 * Events registry BEFORE it can be sent. An unknown name is rejected by the
 * ingestion with HTTP 400 and the stable message prefix
 * `unknown custom event: <name>`. The key pattern is
 * [a-z][a-z0-9_]{0,39} (GA4-compatible). Note that once_per_page_view
 * behaves as unlimited for server callers; the server applies only
 * once_per_session and every_n_seconds.
 */
final class CustomEvent extends AbstractEvent
{
    /** The key pattern every custom event key follows (A9). */
    public const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,39}$/';

    public const ENDPOINT = '/v1/custom-events';

    private string $key;

    /** @var array<string, string|float|int|bool> */
    private array $properties;

    private ?float $value;

    private ?string $currency;

    private ?string $transactionId;

    private ?string $eventId;

    /**
     * @param array<string, string|float|int|bool> $properties
     */
    public function __construct(
        string $key,
        array $properties = [],
        ?float $value = null,
        ?string $currency = null,
        ?string $transactionId = null,
        ?string $eventId = null,
    ) {
        $this->key = $key;
        $this->properties = $properties;
        $this->value = $value;
        $this->currency = $currency;
        $this->transactionId = $transactionId;
        $this->eventId = $eventId;
    }

    public function getEndpoint(): string
    {
        return self::ENDPOINT;
    }

    public function getAction(): string
    {
        return 'custom';
    }

    public function getEventId(): string
    {
        // B4: event_id is REQUIRED by the /v1/custom-events contract (the
        // deduplication key); the SDK generates one when the caller does not
        // supply one, so a plain `new CustomEvent('key')` is accepted.
        if ($this->eventId === null || $this->eventId === '') {
            $this->eventId = $this->generateUuid();
        }

        return $this->eventId;
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0f | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * The /v1/custom-events payload, contract-matched field for field with
     * the Go handler (B4): the caller-supplied event_id drives the
     * ingestion's deduplication (generated when not supplied), and the
     * identity block rides in as user_id (the client id the caller set) and
     * session_id - the user agent and IP fall back to the transport headers
     * on the server side (X-Client-IP / User-Agent), which is where every
     * other /v1 endpoint reads them from.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = [
            'event_name' => $this->key,
            'event_id' => $this->getEventId(),
            'properties' => $this->properties,
        ];

        if ($this->value !== null) {
            $payload['value'] = $this->value;
        }

        if ($this->currency !== null) {
            $payload['currency'] = $this->currency;
        }

        if ($this->transactionId !== null) {
            $payload['transaction_id'] = $this->transactionId;
        }

        if ($this->clientId !== null && $this->clientId !== '') {
            $payload['client_id'] = $this->clientId;
        }

        if ($this->sessionId !== null && $this->sessionId !== '') {
            $payload['session_id'] = $this->sessionId;
        }

        return $payload;
    }

    public function validate(): void
    {
        if (!preg_match(self::KEY_PATTERN, $this->key)) {
            throw new \AxiTrace\Exception\ValidationException(
                sprintf(
                    'Custom event key "%s" must start with a lowercase letter and use only lowercase letters, numbers and underscores, up to 40 characters.',
                    $this->key
                )
            );
        }

        if (count($this->properties) > 30) {
            throw new \AxiTrace\Exception\ValidationException(
                sprintf('Custom event "%s" carries %d properties, at most 30 are accepted.', $this->key, count($this->properties))
            );
        }

        foreach ($this->properties as $name => $value) {
            if (!preg_match('/^[a-zA-Z0-9_]{1,40}$/', (string) $name)) {
                throw new \AxiTrace\Exception\ValidationException(
                    sprintf('Property name "%s" must be 1 to 40 characters of letters, numbers and underscores.', (string) $name)
                );
            }

            if (!is_scalar($value) && $value !== null) {
                throw new \AxiTrace\Exception\ValidationException(
                    sprintf('Property "%s" must be a scalar value.', (string) $name)
                );
            }
        }

        if ($this->value !== null && $this->value < 0) {
            throw new \AxiTrace\Exception\ValidationException('The value must be at least 0.');
        }

        if ($this->currency !== null && !preg_match('/^[A-Z]{3}$/', $this->currency)) {
            throw new \AxiTrace\Exception\ValidationException(
                sprintf('The currency "%s" must be an ISO 4217 code (3 uppercase letters).', $this->currency)
            );
        }

        if ($this->transactionId !== null && mb_strlen($this->transactionId) > 100) {
            throw new \AxiTrace\Exception\ValidationException('The transaction id must be at most 100 characters.');
        }
    }

    public function getClientId(): ?string
    {
        return $this->clientId;
    }

    public function setClientId(?string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }
}
