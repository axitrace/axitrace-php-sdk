<?php

declare(strict_types=1);

namespace AxiTrace\Api;

/**
 * API response wrapper.
 */
class Response
{
    /**
     * @var bool
     */
    private bool $success;

    /**
     * @var string|null
     */
    private ?string $eventId;

    /**
     * @var string|null
     */
    private ?string $action;

    /**
     * @var string|null
     */
    private ?string $error;

    /**
     * @var int
     */
    private int $statusCode;

    /**
     * @var array<string, mixed>
     */
    private array $rawData;

    /**
     * @param bool $success
     * @param int $statusCode
     * @param array<string, mixed> $data
     */
    public function __construct(bool $success, int $statusCode, array $data = [])
    {
        $this->success = $success;
        $this->statusCode = $statusCode;
        $this->rawData = $data;

        $this->eventId = $data['eventId'] ?? null;
        $this->action = $data['action'] ?? null;
        $this->error = $data['error'] ?? null;
    }

    /**
     * Create response from JSON string.
     *
     * @param string $json
     * @param int $statusCode
     * @return self
     */
    public static function fromJson(string $json, int $statusCode): self
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            $data = [];
        }

        $success = ($statusCode >= 200 && $statusCode < 300)
            && (isset($data['success']) ? (bool) $data['success'] : true);

        return new self($success, $statusCode, $data);
    }

    /**
     * Create a successful response.
     *
     * @param string $eventId
     * @param string $action
     * @return self
     */
    public static function success(string $eventId, string $action): self
    {
        return new self(true, 200, [
            'success' => true,
            'eventId' => $eventId,
            'action' => $action,
        ]);
    }

    /**
     * Create an error response.
     *
     * @param string $error
     * @param int $statusCode
     * @return self
     */
    public static function error(string $error, int $statusCode = 400): self
    {
        return new self(false, $statusCode, [
            'success' => false,
            'error' => $error,
        ]);
    }

    /**
     * Check if the request was successful.
     *
     * @return bool
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Get the event ID.
     *
     * @return string|null
     */
    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    /**
     * Get the action name.
     *
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Get the error message.
     *
     * @return string|null
     */
    public function getError(): ?string
    {
        return $this->error;
    }

    /**
     * Get the HTTP status code.
     *
     * @return int
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Get the raw response data.
     *
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }

    /**
     * Get a value from the response data.
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        return $this->rawData[$key] ?? $default;
    }

    /**
     * Convert response to array.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'status_code' => $this->statusCode,
            'event_id' => $this->eventId,
            'action' => $this->action,
            'error' => $this->error,
        ];
    }
}
