<?php

declare(strict_types=1);

namespace AxiTrace\Model\Event;

use AxiTrace\Model\Product;

/**
 * View item list event.
 *
 * Tracks when users view product lists (category pages, search results, etc.).
 */
class ViewItemListEvent extends AbstractEvent
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
     * @var Product[]
     */
    private array $items = [];

    /**
     * {@inheritdoc}
     */
    public function getEndpoint(): string
    {
        return '/v1/catalog/view_list';
    }

    /**
     * {@inheritdoc}
     */
    public function getAction(): string
    {
        return 'view_item_list';
    }

    /**
     * Set item list ID.
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
     * Add a product to the list.
     *
     * @param Product $product
     * @return self
     */
    public function addItem(Product $product): self
    {
        $this->items[] = $product;
        return $this;
    }

    /**
     * Set items from array.
     *
     * @param array<array<string, mixed>> $items
     * @return self
     */
    public function setItems(array $items): self
    {
        $this->items = [];
        foreach ($items as $item) {
            if ($item instanceof Product) {
                $this->items[] = $item;
            } else {
                $this->items[] = Product::fromArray($item);
            }
        }
        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function validate(): void
    {
        $this->validateUserIdentifier();
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

        if (!empty($this->items)) {
            $data['items'] = array_map(function (Product $item) {
                return $item->toArray();
            }, $this->items);
        }

        return $this->addParamsToArray($data);
    }
}
