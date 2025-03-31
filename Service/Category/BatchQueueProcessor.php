<?php

namespace Algolia\AlgoliaSearch\Service\Category;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    public $affectedProductIds = [];

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
     * @throws NoSuchEntityException|LocalizedException
     */
    public function processBatch(int $storeId, ?array $entityIds = null): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        if (count($this->affectedProductIds) > 0) {
            $this->rebuildAffectedProducts($storeId);
        }

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
    protected function rebuildAffectedProducts(int $storeId): void
    {
        if ($this->configHelper->indexProductOnCategoryProductsUpdate($storeId)) {
            $productsPerPage = $this->configHelper->getNumberOfElementByPage();
            foreach (array_chunk($this->affectedProductIds, $productsPerPage) as $chunk) {
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
    protected function processSpecificCategories(array $categoryIds, int $categoriesPerPage, int $storeId): void
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
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    protected function processFullReindex(int $storeId, int $categoriesPerPage): void
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

    /**
     * @param array $affectedProductIds
     * @return void
     */
    public function setAffectedProductIds(array $affectedProductIds): void
    {
        $this->affectedProductIds = $affectedProductIds;
    }
}
