<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Magento\Framework\DataObject;

class IndexOptions extends DataObject implements IndexOptionsInterface
{
    /**
     * Return the current store_id; if the method returns null, the Magento default store will be used
     *
     * @return int|null
     */
    public function getStoreId(): ?int
    {
        return $this->hasData(IndexOptionsInterface::STORE_ID) ?
            (int) $this->getData(IndexOptionsInterface::STORE_ID) :
            null;
    }

    /**
     * Suffix usually refers to the entity to index (_products, _categories, _pages, ...)
     *
     * @return string|null
     */
    public function getIndexSuffix(): ?string
    {
        return $this->hasData(IndexOptionsInterface::INDEX_SUFFIX) ?
            (string) $this->getData(IndexOptionsInterface::INDEX_SUFFIX) :
            null;
    }

    /**
     * Temporary indices can be used in case of full reindexing
     *
     * @return bool
     */
    public function isTemporaryIndex(): bool
    {
        return $this->hasData(IndexOptionsInterface::IS_TMP) && $this->getData(IndexOptionsInterface::IS_TMP);
    }

    /**
     * In case an enforced index name is used, this will override the index names fetched by the IndexNameFetcher service
     *
     * @return string|null
     */
    public function getEnforcedIndexName(): ?string
    {
        return $this->hasData(IndexOptionsInterface::ENFORCED_INDEX_NAME) ?
            (string) $this->getData(IndexOptionsInterface::ENFORCED_INDEX_NAME) :
            null;
    }
}
