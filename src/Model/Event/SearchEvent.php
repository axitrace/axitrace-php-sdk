<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;

/**
 * Search event.
 *
 * Tracks user search queries.
 */
class SearchEvent extends AbstractEvent
{
    /**
     * @var string
     */
    private string $searchTerm;

    /**
     * @var int|null
     */
    private ?int $resultsCount = null;

    /**
     * @var string|null
     */
    private ?string $category = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $filters = null;

    /**
     * @var string|null
     */
    private ?string $sortBy = null;

    /**
     * @var int|null
     */
    private ?int $page = null;

    /**
     * @param string $searchTerm
     */
    public function __construct(string $searchTerm)
    {
        $this->searchTerm = $searchTerm;
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/search';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'search';
    }

    /**
     * Set results count.
     *
     * @param int $count
     * @return self
     */
    public function setResultsCount(int $count): self
    {
        $this->resultsCount = $count;
        return $this;
    }

    /**
     * Set category filter.
     *
     * @param string $category
     * @return self
     */
    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    /**
     * Set applied filters.
     *
     * @param array<string, mixed> $filters
     * @return self
     */
    public function setFilters(array $filters): self
    {
        $this->filters = $filters;
        return $this;
    }

    /**
     * Set sort order.
     *
     * @param string $sortBy
     * @return self
     */
    public function setSortBy(string $sortBy): self
    {
        $this->sortBy = $sortBy;
        return $this;
    }

    /**
     * Set page number.
     *
     * @param int $page
     * @return self
     */
    public function setPage(int $page): self
    {
        $this->page = $page;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if (empty($this->searchTerm)) {
            throw ValidationException::missingRequiredField('search_term', 'search');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = $this->buildBaseArray();

        $data['search_term'] = $this->searchTerm;

        // Build params for optional fields
        $params = $this->params;

        if ($this->resultsCount !== null) {
            $params['results_count'] = $this->resultsCount;
        }

        if ($this->category !== null) {
            $params['category'] = $this->category;
        }

        if ($this->filters !== null) {
            $params['filters'] = $this->filters;
        }

        if ($this->sortBy !== null) {
            $params['sort_by'] = $this->sortBy;
        }

        if ($this->page !== null) {
            $params['page'] = $this->page;
        }

        if (!empty($params)) {
            $data['params'] = $params;
        }

        return $data;
    }
}
