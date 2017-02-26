<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
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
        if (!$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                $this->output->writeln($errorMessage);

                return;
            }

            $this->messageManager->addWarning($errorMessage);

            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if (is_array($productIds) && count($productIds) > 0) {
                $this->queue->addToQueue($this->fullAction, 'rebuildStoreProductIndex', ['store_id' => $storeId, 'product_ids' => $productIds], count($productIds));
                continue;
            }

            $useTmpIndex = $this->configHelper->isQueueActive($storeId);

            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds, $useTmpIndex);
            $size = $collection->getSize();

            if (!empty($productIds)) {
                $size = max(count($productIds), $size);
            }

            $productsPerPage = $this->configHelper->getNumberOfElementByPage();
            $pages = ceil($size / $productsPerPage);

            $this->queue->addToQueue($this->fullAction, 'saveConfigurationToAlgolia', [
                'store_id' => $storeId,
                'useTmpIndex' => $useTmpIndex,
            ]);

            for ($i = 1; $i <= $pages; $i++) {
                $data = [
                    'store_id' => $storeId,
                    'product_ids' => $productIds,
                    'page' => $i,
                    'page_size' => $productsPerPage,
                    'useTmpIndex' => $useTmpIndex,
                ];

                $this->queue->addToQueue($this->fullAction, 'rebuildProductIndex', $data, $productsPerPage);
            }

            if ($useTmpIndex) {
                $this->queue->addToQueue($this->fullAction, 'moveIndex', [
                    'tmpIndexName' => $this->productHelper->getIndexName($storeId, true),
                    'indexName' => $this->productHelper->getIndexName($storeId, false),
                    'store_id' => $storeId,
                ]);
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
