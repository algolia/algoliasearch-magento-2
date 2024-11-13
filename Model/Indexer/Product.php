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
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Product implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ProductHelper $productHelper,
        protected Data $baseHelper,
        protected AlgoliaHelper $algoliaHelper,
        protected ConfigHelper $configHelper,
        protected Queue $queue,
        protected ManagerInterface $messageManager,
        protected ConsoleOutput $output,
        protected DiagnosticsLogger $diag
    ) { }

    /**
     * @throws NoSuchEntityException
     */
    public function execute($productIds)
    {
        if (!$this->configHelper->getApplicationID()
            || !$this->configHelper->getAPIKey()
            || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed:
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addWarning($errorMessage);

            return;
        }

        if ($productIds) {
            $productIds = array_unique(array_merge($productIds, $this->productHelper->getParentProductIds($productIds)));
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->baseHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            $productsPerPage = $this->configHelper->getNumberOfElementByPage();

            if (is_array($productIds) && count($productIds) > 0) {
                foreach (array_chunk($productIds, $productsPerPage) as $chunk) {
                    /** @uses Data::rebuildStoreProductIndex() */
                    $this->queue->addToQueue(
                        Data::class,
                        'rebuildStoreProductIndex',
                        ['storeId' => $storeId, 'productIds' => $chunk],
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
                    'productIds' => $productIds,
                    'page' => $i,
                    'pageSize' => $productsPerPage,
                    'useTmpIndex' => $useTmpIndex,
                ];

                /** @uses Data::rebuildProductIndex() */
                $this->queue->addToQueue(Data::class, 'rebuildProductIndex', $data, $productsPerPage, true);
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
