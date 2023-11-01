<?php

namespace Algolia\AlgoliaSearch\Api;

use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;

interface CategoryVersionLoggerInterface
{
    /**
     * Category save events are store scope sensitive.
     *
     * @param Category $category
     * @param int $storedId
     * @return void
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     */
    public function logCategoryChange(Category $category, int $storedId = 0): void;

    /**
     * Category move events are not store scoped and must be handled differently.
     *
     * @param Category $category
     * @return void
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     */
    public function logCategoryMove(Category $category): void;
}
