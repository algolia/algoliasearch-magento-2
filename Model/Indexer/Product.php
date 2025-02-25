<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
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
use Magento\Store\Model\StoreManagerInterface;

class Product implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{

    public function __construct(
        protected StoreManagerInterface     $storeManager,
        protected ProductHelper             $productHelper,
        protected Data                      $dataHelper,
        protected AlgoliaHelper             $algoliaHelper,
        protected ConfigHelper              $configHelper,
        protected Queue                     $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected DiagnosticsLogger         $diag
    )
    {}

    /**
     * @throws NoSuchEntityException
     */
    public function execute($productIds)
    {
        if (!$this->configHelper->isProductsIndexerEnabled()) {
            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());
        $areParentsLoaded = false;

        foreach ($storeIds as $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

                return;
            }

            if ($productIds && !$areParentsLoaded) {
                $productIds = array_unique(array_merge($productIds, $this->productHelper->getParentProductIds($productIds)));
                $areParentsLoaded = true;
            }

            $productsPerPage = $this->configHelper->getNumberOfElementByPage();

            if (is_array($productIds) && count($productIds) > 0) {
                foreach (array_chunk($productIds, $productsPerPage) as $chunk) {
                    /** @uses ProductIndexBuilder::buildIndexList() */
                    $this->queue->addToQueue(
                        ProductIndexBuilder::class,
                        'buildIndexList',
                        ['storeId' => $storeId, 'entityIds' => $chunk],
                        count($chunk)
                    );
                }

                continue;
            }

            $useTmpIndex = $this->configHelper->isQueueActive($storeId);
            $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex();
            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds, $onlyVisible);

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
                        'entityIds' => $productIds,
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
}
