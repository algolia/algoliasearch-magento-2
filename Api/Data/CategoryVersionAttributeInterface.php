<?php

namespace Algolia\AlgoliaSearch\Api\Data;

use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;

interface CategoryVersionAttributeInterface
{
    /**
     * @param int|null $storeId
     * @return CategoryVersionAttributeInterface
     */
    public function load(int $categoryId, int $storeId = null): CategoryVersionAttributeInterface;

    /**
     * Does this category have versions?
     * @param int|null $storeId
     * @return bool
     */
    public function hasVersions(int $storeId = null): bool;

    /**
     * Get all versions of this category
     * @param int|null $storeId
     * @return CategoryVersionInterface[]
     */
    public function getVersions(int $storeId = null): array;

    /**
     * Get all possible request paths that can be used as category filters
     * @param int|null $storeId
     * @return string[]
     */
    public function getSearchFilters(int $storeId = null): array;

}
