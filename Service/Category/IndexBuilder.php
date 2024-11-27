<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Exception\CategoryReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder
{
    public function __construct(
        protected ConfigHelper      $configHelper,
        protected DiagnosticsLogger $logger,
        protected Emulation         $emulation,
        protected ScopeCodeResolver $scopeCodeResolver,
        protected AlgoliaHelper     $algoliaHelper,
        protected CategoryHelper    $categoryHelper
    ){
        parent::__construct($configHelper, $logger, $emulation, $scopeCodeResolver, $algoliaHelper);
    }

    /**
     * @param int $storeId
     * @param int $page
     * @param int $pageSize
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function buildIndex(int $storeId, int $page, int $pageSize): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);
        $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId, null);
        $this->buildIndexPage($storeId, $collection, $page, $pageSize);
        $this->stopEmulation();
    }

    /**
     * @param $storeId
     * @param $categoryIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function rebuildEntityIds($storeId, $categoryIds = null): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);

        try {
            $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId, $categoryIds);

            $size = $collection->getSize();
            if (!empty($categoryIds)) {
                $size = max(count($categoryIds), $size);
            }

            if ($size > 0) {
                $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
                $page = 1;
                while ($page <= $pages) {
                    $this->buildIndexPage(
                        $storeId,
                        $collection,
                        $page,
                        $this->configHelper->getNumberOfElementByPage(),
                        $categoryIds
                    );
                    $page++;
                }
                unset($indexData);
            }
        } catch (\Exception $e) {
            $this->stopEmulation();
            throw $e;
        }
        $this->stopEmulation();
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException|LocalizedException
     */
    protected function buildIndexPage($storeId, $collection, $page, $pageSize, $categoryIds = null): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $this->algoliaHelper->setStoreId($storeId);
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();
        $indexName = $this->categoryHelper->getIndexName($storeId);
        $indexData = $this->getCategoryRecords($storeId, $collection, $categoryIds);
        if (!empty($indexData['toIndex'])) {
            $this->logger->start('ADD/UPDATE TO ALGOLIA');
            $this->saveObjects($indexData['toIndex'], $indexName);
            $this->logger->log('Product IDs: ' . implode(', ', array_keys($indexData['toIndex'])));
            $this->logger->stop('ADD/UPDATE TO ALGOLIA');
        }

        if (!empty($indexData['toRemove'])) {
            $toRealRemove = $this->getIdsToRealRemove($indexName, $indexData['toRemove']);
            if (!empty($toRealRemove)) {
                $this->logger->start('REMOVE FROM ALGOLIA');
                $this->algoliaHelper->deleteObjects($toRealRemove, $indexName);
                $this->logger->log('Category IDs: ' . implode(', ', $toRealRemove));
                $this->logger->stop('REMOVE FROM ALGOLIA');
            }
        }
        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        unset($collection);
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param int $storeId
     * @param Collection $collection
     * @param array|null $potentiallyDeletedCategoriesIds
     *
     * @return array
     * @throws NoSuchEntityException|LocalizedException
     *
     */
    protected function getCategoryRecords($storeId, $collection, $potentiallyDeletedCategoriesIds = null): array
    {
        $categoriesToIndex = [];
        $categoriesToRemove = [];

        // In $potentiallyDeletedCategoriesIds there might be IDs of deleted products which will not be in a collection
        if (is_array($potentiallyDeletedCategoriesIds)) {
            $potentiallyDeletedCategoriesIds = array_combine(
                $potentiallyDeletedCategoriesIds,
                $potentiallyDeletedCategoriesIds
            );
        }

        /** @var \Magento\Catalog\Model\Category $category */
        foreach ($collection as $category) {
            $category->setStoreId($storeId);
            $categoryId = $category->getId();
            // If $categoryId is in the collection, remove it from $potentiallyDeletedProductsIds
            // so it's not removed without check
            if (isset($potentiallyDeletedCategoriesIds[$categoryId])) {
                unset($potentiallyDeletedCategoriesIds[$categoryId]);
            }

            if (isset($categoriesToIndex[$categoryId]) || isset($categoriesToRemove[$categoryId])) {
                continue;
            }

            try {
                $this->categoryHelper->canCategoryBeReindexed($category, $storeId);
            } catch (CategoryReindexingException $e) {
                $categoriesToRemove[$categoryId] = $categoryId;
                continue;
            }

            $categoriesToIndex[$categoryId] = $this->categoryHelper->getObject($category);
        }

        if (is_array($potentiallyDeletedCategoriesIds)) {
            $categoriesToRemove = array_merge($categoriesToRemove, $potentiallyDeletedCategoriesIds);
        }

        return [
            'toIndex'  => $categoriesToIndex,
            'toRemove' => array_unique($categoriesToRemove),
        ];
    }
}
