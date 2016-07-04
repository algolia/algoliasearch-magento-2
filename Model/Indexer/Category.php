<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento;
use Magento\Store\Model\StoreManagerInterface;

class Category implements Magento\Framework\Indexer\ActionInterface, Magento\Framework\Mview\ActionInterface
{
    private $storeManager;
    private $categoryHelper;
    private $algoliaHelper;
    private $fullAction;
    private $queue;

    public function __construct(StoreManagerInterface $storeManager,
                                CategoryHelper $categoryHelper,
                                Data $helper,
                                AlgoliaHelper $algoliaHelper,
                                Queue $queue)
    {
        $this->fullAction = $helper;
        $this->storeManager = $storeManager;
        $this->categoryHelper = $categoryHelper;
        $this->algoliaHelper = $algoliaHelper;
        $this->queue = $queue;
    }

    public function execute($categoryIds)
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($categoryIds !== null) {
                $indexName = $this->categoryHelper->getIndexName($storeId);
                $this->queue->addToQueue($this->algoliaHelper, 'deleteObjects', ['category_ids' => $categoryIds, 'index_name' => $indexName], count($categoryIds));
            } else {
                $this->queue->addToQueue($this->fullAction, 'saveConfigurationToAlgolia', ['store_id' => $storeId], 1);
            }

            $this->queue->addToQueue($this->fullAction, 'rebuildStoreCategoryIndex', ['store_id' => $storeId, 'category_ids' => $categoryIds], count($categoryIds));
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
