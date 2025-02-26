<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Config\Traits;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Store\Api\Data\StoreInterface;

trait ConfigAssertionsTrait
{
    /**
     * @param StoreInterface|null $store
     * @return int
     * @throws AlgoliaException
     */
    protected function countStoreIndices(StoreInterface $store = null): int
    {
        $indices = $this->algoliaHelper->listIndexes($store->getId());

        $indicesCreatedByTest = 0;

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix . $store->getCode()) === 0) {
                $indicesCreatedByTest++;
            }
        }

        return $indicesCreatedByTest;
    }
}
