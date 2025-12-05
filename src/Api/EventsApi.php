<?php

declare(strict_types=1);

namespace AxiTrace\Api;

use AxiTrace\Client\HttpClient;
use AxiTrace\Exception\ApiException;
use AxiTrace\Exception\AuthenticationException;
use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Event\EventInterface;

/**
 * Events API for sending tracking events.
 */
class EventsApi
{
    /**
     * @var HttpClient
     */
    private HttpClient $httpClient;

    /**
     * @param HttpClient $httpClient
     */
    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Send a single event.
     *
     * @param EventInterface $event
     * @return Response
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function send(EventInterface $event): Response
    {
        // Validate before sending
        $event->validate();

        return $this->httpClient->post(
            $event->getEndpoint(),
            $event->toArray()
        );
    }

    /**
     * Send multiple events.
     *
     * @param EventInterface[] $events
     * @return Response[]
     * @throws ValidationException
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function sendBatch(array $events): array
    {
        $responses = [];

        foreach ($events as $event) {
            $responses[] = $this->send($event);
        }

        return $responses;
    }

    /**
     * Send event data directly (without model).
     *
     * @param string $endpoint
     * @param array<string, mixed> $data
     * @return Response
     * @throws ApiException
     * @throws AuthenticationException
     */
    public function sendRaw(string $endpoint, array $data): Response
    {
        return $this->httpClient->post($endpoint, $data);
    }
}
