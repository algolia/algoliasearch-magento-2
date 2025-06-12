<?php

namespace Algolia\AlgoliaSearch\Model\Observer\Product;

use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize as Cache;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer;

/**
 * Handle the `catalog_product_attribute_update_before` event.
 * Triggered via mass action "Change status" but not "Update attributes"
 */
class CacheCleanAttributeUpdate implements ObserverInterface
{
    public function __construct(
        protected readonly Cache $cache
    ) {}

    public function execute(Observer $observer): void
    {
        $attributes = $observer->getData('attributes_data');
        $productIds = $observer->getData('product_ids');
        $attributesToObserve = ['status'];

        if ($productIds
            && array_intersect(array_keys($attributes), $attributesToObserve)) {
            $this->cache->clear();
        }
    }
}




