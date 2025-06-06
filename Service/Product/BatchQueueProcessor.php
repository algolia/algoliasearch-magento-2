<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\Exception\NoSuchEntityException;

class BatchQueueProcessor implements BatchQueueProcessorInterface
{
    protected bool $areParentsLoaded = false;

    public function __construct(
        protected Data $dataHelper,
        protected ConfigHelper $configHelper,
        protected ProductHelper $productHelper,
        protected Queue $queue,
        protected DiagnosticsLogger $diag,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected ProductIndexBuilder $productIndexBuilder
    ){}

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @return void
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
     */
    public function processBatch(int $storeId, ?array $entityIds = null): void
    {
        if (!$this->dataHelper->isIndexingEnabled($storeId)) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);
            return;
        }

        $productsPerPage = $this->configHelper->getNumberOfElementByPage();

        if (!empty($entityIds)) {
            $this->handleDeltaIndex($entityIds, $storeId, $productsPerPage);
            return;
        }

        $useTmpIndex = $this->configHelper->isQueueActive($storeId);
        $this->syncAlgoliaSettings($storeId, $useTmpIndex);

        $this->handleFullIndex($storeId, $productsPerPage, $useTmpIndex);

        if ($useTmpIndex) {
            $this->moveTempIndex($storeId);
        }
    }

    /**
     * @throws DiagnosticsException
     */
    protected function getCollectionSize(Collection $collection): int
    {
        $this->diag->startProfiling(__METHOD__);
        $size = $collection->getSize();
        $this->diag->stopProfiling(__METHOD__);
        return $size;
    }

    protected function syncAlgoliaSettings(int $storeId, bool $useTmpIndex): void
    {
        /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
        $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', [
            'storeId' => $storeId,
            'useTmpIndex' => $useTmpIndex,
        ], 1, true);
    }

    protected function moveTempIndex(int $storeId): void {
        /** @uses IndexMover::moveIndexWithSetSettings() */
        $this->queue->addToQueue(IndexMover::class, 'moveIndexWithSetSettings', [
            'tmpIndexName' => $this->productHelper->getTempIndexName($storeId),
            'indexName' => $this->productHelper->getIndexName($storeId),
            'storeId' => $storeId,
        ], 1, true);
    }

    protected function handleDeltaIndex(array $entityIds, int $storeId, int $productsPerPage): void
    {
        // TODO: Reassess this member bool
        if (!$this->areParentsLoaded) {
            $entityIds = array_unique(array_merge($entityIds, $this->productHelper->getParentProductIds($entityIds)));
            $this->areParentsLoaded = true;
        }

        foreach (array_chunk($entityIds, $productsPerPage) as $chunk) {
            /** @uses ProductIndexBuilder::buildIndexList() */
            $this->queue->addToQueue(
                ProductIndexBuilder::class,
                'buildIndexList',
                ['storeId' => $storeId, 'entityIds' => $chunk],
                count($chunk)
            );
        }
    }

    /**
     * @throws DiagnosticsException
     */
    protected function handleFullIndex(int $storeId, int $productsPerPage, bool $useTmpIndex): void
    {
        $entityIds = []; // unused in full reindex
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
        $collection = $this->productHelper->getProductCollectionQuery($storeId, $entityIds, $onlyVisible);
        $pages = ceil($this->getCollectionSize($collection) / $productsPerPage);
        for ($i = 1; $i <= $pages; $i++) {
            $data = [
                'storeId' => $storeId,
                'options' => [
                    'entityIds' => $entityIds,
                    'page' => $i,
                    'pageSize' => $productsPerPage,
                    'useTmpIndex' => $useTmpIndex,
                ]
            ];

            /** @uses ProductIndexBuilder::buildIndexFull() */
            $this->queue->addToQueue(
                ProductIndexBuilder::class,
                'buildIndexFull',
                $data,
                $productsPerPage,
                true
            );
        }
    }

    /**
     * @param int $storeId
     * @return void
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function deleteInactiveProducts(int $storeId): void
    {
        if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

            return;
        }

        $this->productIndexBuilder->deleteInactiveProducts($storeId);
    }
}
