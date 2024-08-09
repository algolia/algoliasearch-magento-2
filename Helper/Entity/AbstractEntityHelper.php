<?php

namespace Algolia\AlgoliaSearch\Helper\Entity;

use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;

abstract class AbstractEntityHelper
{
    public function __construct(
        protected IndexNameFetcher $indexNameFetcher
    ) {}

    /**
     * Get the index suffix for this entity type (used to distinguish Magento managed indices in Algolia by entity)
     * @return string
     */
    abstract public function getIndexNameSuffix(): string;

    /**
     * For a given entity helper, return the Algolia index for the specified store
     * @param int $storeId The store index desired
     * @param bool $tmp (Optional) Specify whether to obtain the temp index
     * @throws NoSuchEntityException
     */
    public function getIndexName(int $storeId, bool $tmp = false): string
    {
        return $this->indexNameFetcher->getIndexName($this->getIndexNameSuffix(), $storeId, $tmp);
    }

    /**
     * For a given entity helper, return the temp Algolia index for the specified store
     * (Convenience method, used for handling full reindex via the indexing queue)
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getTempIndexName(int $storeId): string
    {
        return $this->getIndexName($storeId, true);
    }

}
