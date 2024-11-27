<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Category implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public static $affectedProductIds = [];

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected CategoryHelper $categoryHelper,
        protected Data $dataHelper,
        protected Queue $queue,
        protected ConfigHelper $configHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute($categoryIds)
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

                return;
            }

            $this->rebuildAffectedProducts($storeId);

            $categoriesPerPage = $this->configHelper->getNumberOfElementByPage();

            if (is_array($categoryIds) && count($categoryIds) > 0) {
                $this->processSpecificCategories($categoryIds, $categoriesPerPage, $storeId);

                continue;
            }

            $this->processFullReindex($storeId, $categoriesPerPage);
        }
    }

    public function executeFull()
    {
        $this->execute(null);
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        $this->execute([$id]);
    }

    /**
     * @param int $storeId
     */
    private function rebuildAffectedProducts($storeId)
    {
        $affectedProducts = self::$affectedProductIds;
        $affectedProductsCount = count($affectedProducts);

        if ($affectedProductsCount > 0 && $this->configHelper->indexProductOnCategoryProductsUpdate($storeId)) {
            $productsPerPage = $this->configHelper->getNumberOfElementByPage();
            foreach (array_chunk($affectedProducts, $productsPerPage) as $chunk) {
                /** @uses ProductIndexBuilder::rebuildEntityIds() */
                $this->queue->addToQueue(
                    ProductIndexBuilder::class,
                    'rebuildEntityIds',
                    [
                        'storeId' => $storeId,
                        'productIds' => $chunk,
                    ],
                    count($chunk)
                );
            }
        }
    }

    /**
     * @param array $categoryIds
     * @param int $categoriesPerPage
     * @param int $storeId
     */
    private function processSpecificCategories($categoryIds, $categoriesPerPage, $storeId)
    {
        foreach (array_chunk($categoryIds, $categoriesPerPage) as $chunk) {
            /** @uses CategoryIndexBuilder::rebuildEntityIds */
            $this->queue->addToQueue(
                CategoryIndexBuilder::class,
                'rebuildEntityIds',
                [
                    'storeId' => $storeId,
                    'categoryIds' => $chunk,
                ],
                count($chunk)
            );
        }
    }

    /**
     * @param int $storeId
     * @param int $categoriesPerPage
     *
     * @throws Magento\Framework\Exception\LocalizedException
     * @throws Magento\Framework\Exception\NoSuchEntityException
     */
    private function processFullReindex($storeId, $categoriesPerPage)
    {
        /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
        $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', ['storeId' => $storeId]);

        $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId);
        $size = $collection->getSize();

        $pages = ceil($size / $categoriesPerPage);

        for ($i = 1; $i <= $pages; $i++) {
            $data = [
                'storeId' => $storeId,
                'page' => $i,
                'pageSize' => $categoriesPerPage,
            ];

            /** @uses CategoryIndexBuilder::buildIndex() */
            $this->queue->addToQueue(CategoryIndexBuilder::class, 'buildIndex', $data, $categoriesPerPage, true);
        }
    }
}
