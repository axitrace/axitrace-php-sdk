<?php

declare(strict_types=1);

namespace AxiTrace\Exception;

use Exception;
use Throwable;

/**
 * Base exception for all AxiTrace SDK exceptions.
 */
class AxiTraceException extends Exception
{
    /**
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     * @param array<string, mixed> $context
     */
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        array $context = []
    ) {
        parent::__construct($message, $code, $previous);
        $this->context = $context;
    }

    /**
     * Get additional context information about the exception.
     *
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * Set additional context information.
     *
     * @param array<string, mixed> $context
     * @return self
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }
}
