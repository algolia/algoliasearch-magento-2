<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Store\Model\StoreManagerInterface;

class AdditionalSection implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $fullAction;
    private $storeManager;
    private $queue;

    public function __construct(StoreManagerInterface $storeManager, Data $helper, Queue $queue)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->queue = $queue;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
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
