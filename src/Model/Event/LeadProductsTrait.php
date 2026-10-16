<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;
use AxiTrace\Model\ProductNormalizer;

/**
 * Optional products on a lead event (form submit, subscribe, start trial, custom event).
 *
 * Same signatures and line shape as TransactionEvent::addProduct()/setProducts(), but a
 * line that is not a valid purchase line is dropped instead of throwing, so a bad product
 * never stops a lead. The lines are checked when the payload is built, against the event
 * value currency at that moment (see ProductNormalizer::normalizeLeadProducts()).
 */
trait LeadProductsTrait
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $leadProducts = [];

    /**
     * Add a product to the event.
     *
     * Accepts either an AxiTrace\Model\Product instance or a plain array in the transaction
     * product shape (sku, name, finalUnitPrice, quantity, ...). A line without sku, name or
     * a readable price, with a quantity below 1, or in another currency than the event value
     * (or, without a value, than the first valid line) is dropped when the event is sent;
     * the event itself is always sent. unitCost is never sent on a lead event.
     *
     * @param array<string, mixed>|Product $product
     * @return self
     * @throws ValidationException If the product is neither an array nor a Product instance.
     */
    public function addProduct($product): self
    {
        $this->leadProducts[] = ProductNormalizer::fromInput($product, $this->getAction());
        return $this;
    }

    /**
     * Set products, replacing any added before.
     *
     * Each entry is handled the same way as addProduct().
     *
     * @param array<int, array<string, mixed>|Product> $products
     * @return self
     * @throws ValidationException If an entry is neither an array nor a Product instance.
     */
    public function setProducts(array $products): self
    {
        $lines = [];
        foreach ($products as $product) {
            $lines[] = ProductNormalizer::fromInput($product, $this->getAction());
        }
        $this->leadProducts = $lines;
        return $this;
    }

    /**
     * The valid product lines to send, empty when there are none.
     *
     * @param string|null $valueCurrency The event value currency, if any
     * @return array<int, array<string, mixed>>
     */
    protected function buildLeadProducts(?string $valueCurrency): array
    {
        return ProductNormalizer::normalizeLeadProducts($this->leadProducts, $valueCurrency);
    }
}
