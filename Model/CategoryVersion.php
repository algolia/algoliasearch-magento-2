<?php

namespace Algolia\AlgoliaSearch\Model;

use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Model\AbstractExtensibleModel;

class CategoryVersion extends AbstractExtensibleModel implements IdentityInterface, CategoryVersionInterface
{
    const CACHE_TAG = 'algoliasearch_category_version';
    protected $_cacheTag = self::CACHE_TAG;
    protected $_eventPrefix = 'algoliasearch_category_version';

    protected function _construct()
    {
        $this->_init(ResourceModel\CategoryVersion::class);
    }

    /**
     * @inheritDoc
     */
    public function getIdentities(): array
    {
        return [self::CACHE_TAG . '_' . $this->getId()];
    }

    public function getCategoryId(): int
    {
        return $this->getData(CategoryVersionInterface::KEY_CATEGORY_ID);
    }

    public function setCategoryId(int $categoryId): CategoryVersionInterface
    {
        return $this->setData(CategoryVersionInterface::KEY_CATEGORY_ID, $categoryId);
    }

    public function getStoreId(): int
    {
        return $this->getData(CategoryVersionInterface::KEY_STORE_ID);
    }

    public function setStoreId(int $storeId): CategoryVersionInterface
    {
        return $this->setData(CategoryVersionInterface::KEY_STORE_ID, $storeId);
    }

    public function getOldValue(): string
    {
        return $this->getData(CategoryVersionInterface::KEY_OLD_VALUE);
    }

    public function setOldValue(string $val): CategoryVersionInterface
    {
        return $this->setData(CategoryVersionInterface::KEY_OLD_VALUE, $val);
    }

    public function getNewValue(): string
    {
        return $this->getData(CategoryVersionInterface::KEY_NEW_VALUE);
    }

    public function setNewValue(string $val): CategoryVersionInterface
    {
        return $this->setData(CategoryVersionInterface::KEY_NEW_VALUE, $val);
    }

    public function getUpdatedAt(): string
    {
        return $this->getData(CategoryVersionInterface::KEY_UPDATED_AT);
    }

    public function setUpdatedAt(?string $val): CategoryVersionInterface
    {
        return $this->setData(CategoryVersionInterface::KEY_UPDATED_AT, $val);
    }
}
