<?php

namespace Algolia\AlgoliaSearch\Helper\Entity\Product;

use Algolia\AlgoliaSearch\Logger\AlgoliaLogger;
use Algolia\AlgoliaSearch\Model\Cache\Product\IndexCollectionSize as Cache;

class CacheHelper
{
    const ATTRIBUTES_TO_OBSERVE = ['status', 'visibility'];

    public function __construct(
        protected Cache $cache,
        protected AlgoliaLogger $logger
    ) {}

    public function handleBulkAttributeChange(array $productIds, array $attributes, int $storeId)
    {
        if ($productIds
            && array_intersect(array_keys($attributes), self::ATTRIBUTES_TO_OBSERVE)) {
            $this->logger->info(sprintf("Clearing product index collection cache on store ID %d for attributes: %s", $storeId, join(',', array_keys($attributes))));
            $this->cache->clear($storeId ?: null);
        }
    }
}
