<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Model\MessageNormalizer;

/**
 * Optional inquiry message on a lead event (form submit, subscribe, start trial, custom
 * event): the free text the visitor typed, e.g. "Your remarks, requests, suggestions for
 * the order". Sent as a root `message`, plain text, normalized and capped at
 * MessageNormalizer::MAX_LENGTH code points (see MessageNormalizer). It is shown in the
 * AxiTrace Leads workspace as personal data and is never forwarded to ad platforms.
 */
trait LeadMessageTrait
{
    /**
     * @var string|null
     */
    private ?string $leadMessage = null;

    /**
     * Set the visitor's inquiry message (plain text; markup is kept as text).
     *
     * @param string $message
     * @return self
     */
    public function setMessage(string $message): self
    {
        $this->leadMessage = $message;
        return $this;
    }

    /**
     * The message as it will be sent (normalized), or null when none is set or nothing is
     * left after normalization.
     *
     * @return string|null
     */
    public function getMessage(): ?string
    {
        if ($this->leadMessage === null) {
            return null;
        }

        $message = MessageNormalizer::normalize($this->leadMessage);

        return $message === '' ? null : $message;
    }

    /**
     * Add the root `message` to a payload when there is one.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    protected function withLeadMessage(array $payload): array
    {
        $message = $this->getMessage();
        if ($message !== null) {
            $payload['message'] = $message;
        }

        return $payload;
    }
}
