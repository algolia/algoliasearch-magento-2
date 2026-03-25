<?php

namespace Algolia\AlgoliaSearch\Model\Data;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Magento\Framework\DataObject;

class IndexOptions extends DataObject implements IndexOptionsInterface
{
    /**
     * Return the current store_id; if the method returns null, the Magento default store will be used
     *
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
     */
    public function isTemporaryIndex(): bool
    {
        return $this->hasData(IndexOptionsInterface::IS_TMP) && $this->getData(IndexOptionsInterface::IS_TMP);
    }


    /**
     * Returns the final index name computed by the IndexNameFetcher
     *
     */
    public function getIndexName(): ?string
    {
        return $this->getData(IndexOptionsInterface::INDEX_NAME);
    }

    public function setIndexName(string $indexName): void
    {
        $this->setData(IndexOptionsInterface::INDEX_NAME, $indexName);
    }
}
