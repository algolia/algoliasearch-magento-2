<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Magento\Framework\DataObject;

class IndexOptions extends DataObject implements IndexOptionsInterface
{
    public function getStoreId(): ?int
    {
        return $this->hasData(IndexOptionsInterface::STORE_ID) ?
            (int) $this->getData(IndexOptionsInterface::STORE_ID) :
            null;
    }

    public function getIndexSuffix(): ?string
    {
        return $this->hasData(IndexOptionsInterface::INDEX_SUFFIX) ?
            (string) $this->getData(IndexOptionsInterface::INDEX_SUFFIX) :
            null;
    }

    public function isTmp(): bool
    {
        return $this->hasData(IndexOptionsInterface::IS_TMP) ?
            (string) $this->getData(IndexOptionsInterface::IS_TMP) :
            false;
    }

    public function getEnforcedIndexName(): ?string
    {
        return $this->hasData(IndexOptionsInterface::ENFORCED_INDEX_NAME) ?
            (string) $this->getData(IndexOptionsInterface::ENFORCED_INDEX_NAME) :
            null;
    }
}
