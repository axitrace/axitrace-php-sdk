<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Exception\ValidationException;
use AxiTrace\Model\Product;

/**
 * Select item event.
 *
 * Tracks when users click/select a product from a list.
 */
class SelectItemEvent extends AbstractEvent
{
    /**
     * @var string|null
     */
    private ?string $itemListId = null;

    /**
     * @var string|null
     */
    private ?string $itemListName = null;

    /**
     * @var Product|null
     */
    private ?Product $item = null;

    /**
     * @param Product|null $item
     */
    public function __construct(?Product $item = null)
    {
        $this->item = $item;
    }

    /**
     * Create from product data array.
     *
     * @param array<string, mixed> $itemData
     * @return self
     */
    public static function fromArray(array $itemData): self
    {
        return new self(Product::fromArray($itemData));
    }

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/catalog/select_item';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'select_item';
    }

    /**
     * Set the selected item.
     *
     * @param Product $item
     * @return self
     */
    public function setItem(Product $item): self
    {
        $this->item = $item;
        return $this;
    }

    /**
     * Set item list ID (the list where item was selected from).
     *
     * @param string $itemListId
     * @return self
     */
    public function setItemListId(string $itemListId): self
    {
        $this->itemListId = $itemListId;
        return $this;
    }

    /**
     * Set item list name.
     *
     * @param string $itemListName
     * @return self
     */
    public function setItemListName(string $itemListName): self
    {
        $this->itemListName = $itemListName;
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();

        if ($this->item === null) {
            throw ValidationException::missingRequiredField('items', 'select_item');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function toArray(): array
    {
        $data = $this->buildBaseArray();

        if ($this->itemListId !== null) {
            $data['item_list_id'] = $this->itemListId;
        }

        if ($this->itemListName !== null) {
            $data['item_list_name'] = $this->itemListName;
        }

        if ($this->item !== null) {
            $data['items'] = [$this->item->toArray()];
        }

        return $this->addParamsToArray($data);
    }
}
