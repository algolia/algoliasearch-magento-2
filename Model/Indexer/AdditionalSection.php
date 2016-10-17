<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Framework\Message\ManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

class AdditionalSection implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $fullAction;
    private $storeManager;
    private $queue;
    private $configHelper;
    private $messageManager;

    public function __construct(StoreManagerInterface $storeManager, Data $helper, Queue $queue, ConfigHelper $configHelper, ManagerInterface $messageManager)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->queue = $queue;
        $this->configHelper = $configHelper;
        $this->messageManager = $messageManager;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
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

        foreach ($storeIds as $storeId) {
            $this->queue->addToQueue($this->fullAction, 'rebuildStoreAdditionalSectionsIndex', ['store_id' => $storeId], 1);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
