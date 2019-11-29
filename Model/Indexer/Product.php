<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Model\IndexMover;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class Product implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    private $productHelper;
    private $algoliaHelper;
    private $fullAction;
    private $configHelper;
    private $queue;
    private $messageManager;
    private $output;

    public function __construct(
        StoreManagerInterface $storeManager,
        ProductHelper $productHelper,
        Data $helper,
        AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        Queue $queue,
        ManagerInterface $messageManager,
        ConsoleOutput $output
    ) {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->productHelper = $productHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->configHelper = $configHelper;
        $this->queue = $queue;
        $this->messageManager = $messageManager;
        $this->output = $output;
    }

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
            if ($this->fullAction->isIndexingEnabled($storeId) === false) {
                continue;
            }

            $productsPerPage = $this->configHelper->getNumberOfElementByPage();

            if (is_array($productIds) && count($productIds) > 0) {
                foreach (array_chunk($productIds, $productsPerPage) as $chunk) {
                    /** @uses Data::rebuildStoreProductIndex() */
                    $this->queue->addToQueue(
                        Data::class,
                        'rebuildStoreProductIndex',
                        ['store_id' => $storeId, 'product_ids' => $chunk],
                        count($chunk)
                    );
                }

                continue;
            }

            $useTmpIndex = $this->configHelper->isQueueActive($storeId);

            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds, $useTmpIndex);
            $size = $collection->getSize();

            $pages = ceil($size / $productsPerPage);

            /** @uses IndicesConfigurator::saveConfigurationToAlgolia() */
            $this->queue->addToQueue(IndicesConfigurator::class, 'saveConfigurationToAlgolia', [
                'store_id' => $storeId,
                'useTmpIndex' => $useTmpIndex,
            ], 1, true);

            for ($i = 1; $i <= $pages; $i++) {
                $data = [
                    'store_id' => $storeId,
                    'product_ids' => $productIds,
                    'page' => $i,
                    'page_size' => $productsPerPage,
                    'useTmpIndex' => $useTmpIndex,
                ];

                /** @uses Data::rebuildProductIndex() */
                $this->queue->addToQueue(Data::class, 'rebuildProductIndex', $data, $productsPerPage, true);
            }

            if ($useTmpIndex) {
                $suffix = $this->productHelper->getIndexNameSuffix();

                /** @uses IndexMover::moveIndexWithSetSettings() */
                $this->queue->addToQueue(IndexMover::class, 'moveIndexWithSetSettings', [
                    'tmpIndexName' => $this->fullAction->getIndexName($suffix, $storeId, true),
                    'indexName' => $this->fullAction->getIndexName($suffix, $storeId),
                    'store_id' => $storeId,
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
