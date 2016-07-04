<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Store\Model\StoreManagerInterface;

class Page implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $fullAction;
    private $storeManager;
    private $pageHelper;
    private $algoliaHelper;
    private $queue;

    public function __construct(StoreManagerInterface $storeManager,
                                PageHelper $pageHelper,
                                Data $helper,
                                AlgoliaHelper $algoliaHelper,
                                Queue $queue)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->pageHelper = $pageHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->queue = $queue;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $this->fullAction->rebuildStorePageIndex($storeId);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
