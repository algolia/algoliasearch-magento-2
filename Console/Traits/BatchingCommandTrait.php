<?php

namespace Algolia\AlgoliaSearch\Console\Traits;

use Magento\Catalog\Model\ResourceModel\Product\Collection;

trait BatchingCommandTrait
{
    /**
     * Recommended Max batch size
     * https://www.algolia.com/doc/guides/sending-and-managing-data/send-and-update-your-data/how-to/sending-records-in-batches/
     */
    const MAX_BATCH_SIZE = 10000000; //10MB

    /**
     * Arbitrary default margin to ensure not to exceed recommended batch size
     */
    const DEFAULT_MARGIN = 25;

    /**
     * Arbitrary increased margin to ensure not to exceed recommended batch size when catalog is a mix between complex and other product types
     * (i.e. with a lot of record sizes variations)
     */
    const INCREASED_MARGIN = 50;

    const PRODUCTS_SIMPLE_TYPES = [
        'simple',
        'downloadable',
        'virtual',
        'giftcard'
    ];

    const PRODUCTS_COMPLEX_TYPES = [
        'configurable',
        'grouped',
        'bundle'
    ];

    /**
     * @param int $storeId
     * @param array $productTypes
     * @return Collection
     */
    protected function getProductsCollectionForStore(int $storeId, array $productTypes = []): Collection
    {
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
        $collection = $this->productHelper->getProductCollectionQuery($storeId, null, $onlyVisible);
        if (count($productTypes) > 0) {
            $collection->addAttributeToFilter('type_id', ['in' => $productTypes]);
        }

        return $collection;
    }
}
