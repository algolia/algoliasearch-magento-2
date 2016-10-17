<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class Category implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    private $categoryHelper;
    private $algoliaHelper;
    private $fullAction;
    private $queue;
    private $configHelper;
    private $messageManager;

    public static $affectedProductIds = [];

    public function __construct(StoreManagerInterface $storeManager,
                                CategoryHelper $categoryHelper,
                                Data $helper,
                                AlgoliaHelper $algoliaHelper,
                                Queue $queue,
                                ConfigHelper $configHelper,
                                ManagerInterface $messageManager)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->categoryHelper = $categoryHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
    }

    public function execute($categoryIds)
    {
        if (!$this->configHelper->getApplicationID() || !$this->configHelper->getAPIKey() || !$this->configHelper->getSearchOnlyAPIKey()) {
            $errorMessage = 'Algolia reindexing failed: You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

            if (php_sapi_name() === 'cli') {
                throw new \Exception($errorMessage);
            }

            $this->messageManager->addErrorMessage($errorMessage);

            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());
        $affectedProductsCount = count(self::$affectedProductIds);

        foreach ($storeIds as $storeId) {
            if ($categoryIds !== null) {
                $indexName = $this->categoryHelper->getIndexName($storeId);
                $this->queue->addToQueue($this->fullAction, 'deleteObjects', ['category_ids' => $categoryIds, 'index_name' => $indexName], count($categoryIds));
            } else {
                $this->queue->addToQueue($this->fullAction, 'saveConfigurationToAlgolia', ['store_id' => $storeId], 1);
            }

            $this->queue->addToQueue($this->fullAction, 'rebuildStoreCategoryIndex', ['store_id' => $storeId, 'category_ids' => $categoryIds], count($categoryIds));

            if ($affectedProductsCount > 0 && $this->configHelper->indexProductOnCategoryProductsUpdate($storeId)) {
                $this->queue->addToQueue($this->fullAction, 'rebuildStoreProductIndex', ['store_id' => $storeId, 'product_ids' => self::$affectedProductIds], $affectedProductsCount);
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
