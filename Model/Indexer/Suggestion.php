<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Store\Model\StoreManagerInterface;

class Suggestion implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $fullAction;
    private $storeManager;
    private $suggestionHelper;
    private $algoliaHelper;
    private $queue;

    public function __construct(StoreManagerInterface $storeManager,
                                SuggestionHelper $suggestionHelper,
                                Data $helper,
                                AlgoliaHelper $algoliaHelper,
                                Queue $queue)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->suggestionHelper = $suggestionHelper;
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
            $this->queue->addToQueue($this->fullAction, 'rebuildStoreSuggestionIndex', ['store_id' => $storeId], 1);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
