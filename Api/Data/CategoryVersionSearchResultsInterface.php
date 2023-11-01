<?php

namespace Algolia\AlgoliaSearch\Api\Data;

use Magento\Framework\Api\SearchResultsInterface;

interface CategoryVersionSearchResultsInterface extends SearchResultsInterface
{

    /**
     * Get list of category versions
     *
     * @return CategoryVersionInterface[]
     */
    public function getItems();

    /**
     * Set category version list
     *
     * @param CategoryVersionInterface[] $items
     * @return $this
     */
    public function setItems(array $items);
}
