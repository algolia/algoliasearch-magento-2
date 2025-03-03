<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\QueueBuilder as CategoryQueueBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Category implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{


    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected CategoryQueueBuilder $categoryQueueBuilder
    ) {}

    public function execute($categoryIds)
    {
        if (!$this->configHelper->isCategoriesIndexerEnabled()) {
            return;
        }

        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->categoryQueueBuilder->buildQueue($storeId, $categoryIds);
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
