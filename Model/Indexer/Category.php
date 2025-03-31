<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Category\BatchQueueProcessor as CategoryBatchQueueProcessor;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This indexer is now disabled by default, prefer use the `bin/magento algolia:reindex:categories` command instead
 * If you want to re-enable it, you can do it in the Magento configuration ("Algolia Search > Indexing Manager" section)
 */
class Category implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public static $affectedProductIds = [];

    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected CategoryBatchQueueProcessor $categoryBatchQueueProcessor
    ) {}

    public function execute($categoryIds)
    {
        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->categoryBatchQueueProcessor->processBatch($storeId, $categoryIds);
        }
    }

    public function executeFull()
    {
        if (!$this->configHelper->isCategoriesIndexerEnabled()) {
            return;
        }

        $this->execute(null);
    }

    public function executeList(array $ids)
    {
        $this->execute($ids);
    }

    public function executeRow($id)
    {
        if (count(self::$affectedProductIds)) {
            $this->categoryBatchQueueProcessor->setAffectedProductIds(self::$affectedProductIds);
        }
        $this->execute([$id]);
    }
}
