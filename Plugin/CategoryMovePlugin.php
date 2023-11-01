<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Algolia\AlgoliaSearch\Api\CategoryVersionLoggerInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class CategoryMovePlugin
{
    /** @var StoreManagerInterface */
    protected StoreManagerInterface $storeManager;

    /** @var CategoryVersionLoggerInterface */
    protected CategoryVersionLoggerInterface $categoryVersionLogger;

    public function __construct(
        StoreManagerInterface          $storeManager,
        CategoryVersionLoggerInterface $categoryVersionLogger
    )
    {
        $this->storeManager = $storeManager;
        $this->categoryVersionLogger = $categoryVersionLogger;
    }

    /**
     * @param Category $subject
     * @param Category $result
     * @param int $parentId
     * @param int|null $afterCategoryId
     * @return Category
     * @throws AlreadyExistsException
     * @throws NoSuchEntityException
     */
    public function afterMove(Category $subject, Category $result, int $parentId, null|int $afterCategoryId)
    {
        $this->categoryVersionLogger->logCategoryMove($result);
        return $result;
    }
}
