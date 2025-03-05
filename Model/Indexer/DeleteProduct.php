<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This indexer is now disabled by default, prefer use the `bin/magento algolia:reindex:delete_products` command instead
 * If you want to re-enable it, you can do it in the Magento configuration ("Algolia Search > Indexing Manager" section)
 */
class DeleteProduct implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected ProductBatchQueueProcessor $productBatchQueueProcessor
    ) {}

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        if (!$this->configHelper->isDeleteProductsIndexerEnabled()) {
            return;
        }

        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->productBatchQueueProcessor->deleteInactiveProducts($storeId);
        }
    }

    public function executeList(array $ids)
    {
        return $this;
    }

    public function executeRow($id)
    {
        return $this;
    }
}
