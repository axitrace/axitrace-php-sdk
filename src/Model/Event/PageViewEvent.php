<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Page view event.
 *
 * Tracks when a user views a page on your site.
 */
class PageViewEvent extends AbstractEvent
{
    /**
     * @var string
     */
    private string $url;

    /**
     * @var string|null
     */
    private ?string $title = null;

    /**
     * @var string|null
     */
    private ?string $referrer = null;

    /**
     * @var string|null
     */
    private ?string $eventSalt = null;

    /**
     * @param string $url
     */
    public function __construct(string $url)
    {
        $this->url = $url;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/page/view';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'page_view';
    }

    /**
     * Set page title.
     *
     * @param string $title
     * @return self
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    /**
     * Set referrer URL.
     *
     * @param string $referrer
     * @return self
     */
    public function setReferrer(string $referrer): self
    {
        $this->referrer = $referrer;
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
        $this->validateUserIdentifier();

        if (empty($this->url)) {
            throw ValidationException::missingRequiredField('url', 'page_view');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        // Build API-compatible structure
        $data = [
            'label' => 'page_view',
            'client' => $this->buildClientObject(),
        ];

        if ($this->sessionId !== null) {
            $data['sessionId'] = $this->sessionId;
        }

        if ($this->eventSalt !== null) {
            $data['eventSalt'] = $this->eventSalt;
        }

        // Build params object with url and other data
        $params = ['url' => $this->url];

        if ($this->title !== null) {
            $params['title'] = $this->title;
        }

        if ($this->referrer !== null) {
            $params['referrer'] = $this->referrer;
        }

        // Add Facebook and other tracking params
        $params = array_merge($params, $this->params);

        $data['params'] = $params;

        return $data;
    }
}
