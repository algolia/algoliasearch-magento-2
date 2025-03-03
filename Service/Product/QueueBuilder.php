<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Builder\QueueBuilderInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class QueueBuilder implements QueueBuilderInterface
{
    protected bool $areParentsLoaded = false;

    public function __construct(
        protected Data $dataHelper,
        protected ConfigHelper $configHelper,
        protected ProductHelper $productHelper,
        protected Queue $queue,
        protected DiagnosticsLogger $diag,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    ){}

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @return void
     * @throws NoSuchEntityException
     * @throws DiagnosticsException
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

        if ($entityIds && !$this->areParentsLoaded) {
            $entityIds = array_unique(array_merge($entityIds, $this->productHelper->getParentProductIds($entityIds)));
            $this->areParentsLoaded = true;
        }

        $productsPerPage = $this->configHelper->getNumberOfElementByPage();

        if (is_array($entityIds) && count($entityIds) > 0) {
            foreach (array_chunk($entityIds, $productsPerPage) as $chunk) {
                /** @uses ProductIndexBuilder::buildIndexList() */
                $this->queue->addToQueue(
                    ProductIndexBuilder::class,
                    'buildIndexList',
                    ['storeId' => $storeId, 'entityIds' => $chunk],
                    count($chunk)
                );
            }

            return;
        }

        $useTmpIndex = $this->configHelper->isQueueActive($storeId);
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
        $collection = $this->productHelper->getProductCollectionQuery($storeId, $entityIds, $onlyVisible);

        $timerName = __METHOD__ . ' (Get product collection size)';
        $this->diag->startProfiling($timerName);
        $size = $collection->getSize();
        $this->diag->stopProfiling($timerName);

        $pages = ceil($size / $productsPerPage);

        /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
        $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', [
            'storeId' => $storeId,
            'useTmpIndex' => $useTmpIndex,
        ], 1, true);
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

        if ($useTmpIndex) {
            /** @uses IndexMover::moveIndexWithSetSettings() */
            $this->queue->addToQueue(IndexMover::class, 'moveIndexWithSetSettings', [
                'tmpIndexName' => $this->productHelper->getTempIndexName($storeId),
                'indexName' => $this->productHelper->getIndexName($storeId),
                'storeId' => $storeId,
            ], 1, true);
        }
    }
}
