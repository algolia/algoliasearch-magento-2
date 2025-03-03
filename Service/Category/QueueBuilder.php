<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Api\Builder\QueueBuilderInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class QueueBuilder implements QueueBuilderInterface
{
    public static $affectedProductIds = [];

    public function __construct(
        protected Data $dataHelper,
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected CategoryHelper $categoryHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ){}

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @return void
     * @throws NoSuchEntityException
     */
    public function buildQueue(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        $this->rebuildAffectedProducts($storeId);

        $categoriesPerPage = $this->configHelper->getNumberOfElementByPage();

        if (is_array($entityIds) && count($entityIds) > 0) {
            $this->processSpecificCategories($entityIds, $categoriesPerPage, $storeId);

            return;
        }

        $this->processFullReindex($storeId, $categoriesPerPage);
    }

    /**
     * @param int $storeId
     */
    protected function rebuildAffectedProducts($storeId)
    {
        $affectedProducts = self::$affectedProductIds;
        $affectedProductsCount = count($affectedProducts);

        if ($affectedProductsCount > 0 && $this->configHelper->indexProductOnCategoryProductsUpdate($storeId)) {
            $productsPerPage = $this->configHelper->getNumberOfElementByPage();
            foreach (array_chunk($affectedProducts, $productsPerPage) as $chunk) {
                /** @uses ProductIndexBuilder::buildIndexList() */
                $this->queue->addToQueue(
                    ProductIndexBuilder::class,
                    'buildIndexList',
                    [
                        'storeId' => $storeId,
                        'entityIds' => $chunk,
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
    protected function processSpecificCategories($categoryIds, $categoriesPerPage, $storeId)
    {
        foreach (array_chunk($categoryIds, $categoriesPerPage) as $chunk) {
            /** @uses CategoryIndexBuilder::buildIndexList */
            $this->queue->addToQueue(
                CategoryIndexBuilder::class,
                'buildIndexList',
                [
                    'storeId' => $storeId,
                    'entityIds' => $chunk,
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
    protected function processFullReindex($storeId, $categoriesPerPage)
    {
        /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
        $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', ['storeId' => $storeId]);

        $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId);
        $size = $collection->getSize();

        $pages = ceil($size / $categoriesPerPage);

        for ($i = 1; $i <= $pages; $i++) {
            $data = [
                'storeId' => $storeId,
                'options' => [
                    'page' => $i,
                    'pageSize' => $categoriesPerPage,
                ]
            ];

            /** @uses CategoryIndexBuilder::buildIndexFull() */
            $this->queue->addToQueue(
                CategoryIndexBuilder::class,
                'buildIndexFull',
                $data,
                $categoriesPerPage,
                true
            );
        }
    }
}
