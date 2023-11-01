<?php

namespace Algolia\AlgoliaSearch\Api\Data;

use Magento\Framework\Api\ExtensibleDataInterface;

interface CategoryVersionInterface extends ExtensibleDataInterface
{
    const KEY_CATEGORY_ID = 'entity_id';
    const KEY_STORE_ID = 'store_id';
    const KEY_OLD_VALUE = 'old_value';
    const KEY_NEW_VALUE = 'new_value';
    const KEY_CREATED_AT = 'created_at';
    const KEY_UPDATED_AT = 'updated_at';
    const KEY_INDEXED_AT = 'indexed_at';
    const KEY_RESOLVED_AT = 'resolved_at';

    public function getCategoryId(): int;
    public function setCategoryId(int $categoryId): CategoryVersionInterface;

    public function getStoreId(): int;
    public function setStoreId(int $storeId): CategoryVersionInterface;

    public function getOldValue(): string;
    public function setOldValue(string $val): CategoryVersionInterface;

    public function getNewValue(): string;
    public function setNewValue(string $val): CategoryVersionInterface;

    public function getUpdatedAt(): string;
    public function setUpdatedAt(?string $val): CategoryVersionInterface;

}
