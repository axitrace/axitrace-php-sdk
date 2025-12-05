<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Interface for all AxiTrace events.
 */
interface EventInterface
{
    /**
     * Get the API endpoint for this event.
     *
     * @return string
     */
    public function getEndpoint(): string;

    /**
     * Get the event action name.
     *
     * @return string
     */
    public function getAction(): string;

    /**
     * Convert event to array for API request.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Validate the event data.
     *
     * @throws ValidationException
     */
    public function validate(): void;
}
