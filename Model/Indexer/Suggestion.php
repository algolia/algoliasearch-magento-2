<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Indexer\SaveHandler\Batch;

class Suggestion implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    protected $suggestionHelper;
    protected $algoliaHelper;
    protected $batch;
    protected $fullAction;

    public function __construct(StoreManagerInterface $storeManager,
                                SuggestionHelper $suggestionHelper,
                                Data $helper,
                                Batch $batch,
                                AlgoliaHelper $algoliaHelper)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->suggestionHelper = $suggestionHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->batch = $batch;
    }

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            $this->fullAction->rebuildStoreSuggestionIndex($storeId);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
