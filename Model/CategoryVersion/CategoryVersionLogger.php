<?php

namespace Algolia\AlgoliaSearch\Model\CategoryVersion;

use Algolia\AlgoliaSearch\Api\CategoryVersionLoggerInterface;
use Algolia\AlgoliaSearch\Api\CategoryVersionRepositoryInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionInterface;
use Algolia\AlgoliaSearch\Api\Data\CategoryVersionSearchResultsInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\Data\CategoryInterface;
use Magento\Catalog\Api\Data\CategorySearchResultsInterface;
use Magento\Catalog\Model\Category;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManager;

class CategoryVersionLogger implements CategoryVersionLoggerInterface
{
    public const MIN_CATEGORY_LEVEL = 2;
    public const DEFAULT_STORE = 0;
    /**
     * @var CategoryRepositoryInterface
     */
    protected CategoryRepositoryInterface $categoryRepository;

    /**
     * @var ConfigHelper
     */
    protected ConfigHelper $config;

    /** @var StoreManager  */
    protected StoreManager $storeManager;

    /** @var CategoryVersionRepositoryInterface  */
    protected CategoryVersionRepositoryInterface $categoryVersionRepository;

    /** @var SearchCriteriaBuilder */
    protected SearchCriteriaBuilder $searchCriteriaBuilder;

    /** @var CategoryInterface[] $categoryCache */
    protected array $categoryCache = [];

    public function __construct(
        ConfigHelper                       $config,
        CategoryRepositoryInterface        $categoryRepository,
        CategoryVersionRepositoryInterface $categoryVersionRepository,
        StoreManager                       $storeManager,
        SearchCriteriaBuilder              $searchCriteriaBuilder
    )
    {
        $this->config = $config;
        $this->categoryRepository = $categoryRepository;
        $this->categoryVersionRepository = $categoryVersionRepository;
        $this->storeManager = $storeManager;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
    }

    /**
     * @inheritDoc
     */
    public function logCategoryChange(Category $category, int $storedId = 0): void
    {
        if (!$this->config->isCategoryVersionTrackingEnabled($storedId)) return;

        foreach ($this->getStoreIds($category, $storedId) as $id) {
            $newPath = $this->getNewCategoryPath($category, $id);
            $oldPath = $this->getOldCategoryPath($category, $id);
            /** @var CategoryVersionInterface $version */
            $version = $this->getCategoryVersion($category->getId(), $oldPath, $id);
            $version->setCategoryId($category->getId());
            $version->setStoreId($id);
            $version->setOldValue($oldPath);
            $version->setNewValue($newPath);
            $version->setUpdatedAt(null);
            $this->categoryVersionRepository->save($version);
        }
    }

    /**
     * @inheritDoc
     */
    public function logCategoryMove(Category $category): void {
        $defaultStoreId = self::DEFAULT_STORE;
        if (!$this->config->isCategoryVersionTrackingEnabled($defaultStoreId)) return;

        foreach ($this->getStoreIds($category, $defaultStoreId, false) as $id) {
            /** @var CategoryInterface */
            $scopedCategory = $this->getCachedCategory($category->getId(), $id);
            $newPath = $this->getNewCategoryPath($scopedCategory, $id);
            $oldPath = $this->getCategoryPath(
                $scopedCategory->getName(),
                $this->getPathIds($category->getOrigData(CategoryInterface::KEY_PATH)),
                $id
            );
            /** @var CategoryVersionInterface */
            $version = $this->getCategoryVersion($category->getId(), $oldPath, $id);
            $version->setCategoryId($category->getId());
            $version->setStoreId($id);
            $version->setOldValue($oldPath);
            $version->setNewValue($newPath);
            $version->setUpdatedAt(null);
            $this->categoryVersionRepository->save($version);
        }
    }

    /**
     * Get deduplicated record
     * @param int $categoryId
     * @param string $path
     * @param int $storeId
     * @return CategoryVersionInterface
     */
    protected function getCategoryVersion(int $categoryId, string $path, int $storeId): CategoryVersionInterface {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(CategoryVersionInterface::KEY_CATEGORY_ID, $categoryId)
            ->addFilter(CategoryVersionInterface::KEY_OLD_VALUE, $path)
            ->addFilter(CategoryVersionInterface::KEY_STORE_ID, $storeId);
        /* @var CategoryVersionSearchResultsInterface */
        $results = $this->categoryVersionRepository->getList($searchCriteria->create());
        if ($results->getTotalCount()) {
            return array_values($results->getItems())[0];
        } else {
            return $this->categoryVersionRepository->getNew();
        }
    }

    /**
     * Get the new category path for the category being updated
     * @param Category $category
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getNewCategoryPath(Category $category, int $storeId): string
    {
        return $this->getCategoryPath($category->getName(), $category->getPathIds(), $storeId);
    }

    /**
     * Get the old category path for a standard category save operation
     * @param Category $category
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getOldCategoryPath(Category $category, int $storeId): string
    {
        return $this->getCategoryPath(
            $category->getOrigData(CategoryInterface::KEY_NAME),
            $this->getPathIds($category->getOrigData(CategoryInterface::KEY_PATH)),
            $storeId
        );
    }

    /**
     * Get the category path name (used for category page IDs)
     * @param string $categoryName
     * @param array $pathIds
     * @param int $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    protected function getCategoryPath(string $categoryName, array $pathIds, int $storeId): string
    {
        $path = $categoryName;
        foreach (array_slice(array_reverse($pathIds), 1) as $treeCategoryId) {
            $level = $this->getCachedCategory($treeCategoryId, $storeId);
            if ((int) $level->getLevel() < self::MIN_CATEGORY_LEVEL) break;
            $path = $level->getName() . $this->config->getCategorySeparator() . $path;
        }
        return $path;
    }

    /**
     * @param int $categoryId
     * @param int $storeId
     * @return CategoryInterface
     * @throws NoSuchEntityException
     */
    protected function getCachedCategory(int $categoryId, int $storeId): CategoryInterface
    {
        $key = "$categoryId-$storeId";
        if (!array_key_exists($key, $this->categoryCache)) {
            $this->categoryCache[$key] = $this->categoryRepository->get($categoryId, $storeId);
        }
        return $this->categoryCache[$key];
    }

    /**
     * For extracting path ids from orig path data
     * @param string|null $path
     * @return int[]
     */
    protected function getPathIds(string|null $path): array
    {
        return $path !== null ? explode('/', $path) : [];
    }

    /**
     * Get applicable store ID's to log versions for - either the specified store, all stores or all non overridden stores
     * @param CategoryInterface $category
     * @param int $storeId - specific store or 0 (for default)
     * @param bool $filterOverride - whether to include overridden stores
     * @return array|int[]
     * @throws NoSuchEntityException
     */
    protected function getStoreIds(CategoryInterface $category, int $storeId, bool $filterOverride = true): array
    {
        //specified store
        if ($storeId) return [$storeId];

        $storeIds = [];
        foreach (array_keys($this->storeManager->getStores()) as $id) {
            if ($this->config->isEnabledBackend($id)
                && $this->config->isCategoryVersionTrackingEnabled($id)
                && (!$filterOverride || !$this->isCategoryOverridden($category, $id))) {
                $storeIds[] = $id;
            }
        }
        return $storeIds;
    }

    /**
     * @param CategoryInterface $defaultCategory
     * @param int $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    protected function isCategoryOverridden(CategoryInterface $defaultCategory, int $storeId): bool
    {
        $storeScopedCategory = $this->categoryRepository->get($defaultCategory->getId(), $storeId);
        return $storeScopedCategory->getName() !== $defaultCategory->getName();
    }
}
