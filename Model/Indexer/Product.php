<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This indexer is now disabled by default, prefer use the `bin/magento algolia:reindex:products` command instead
 * If you want to re-enable it, you can do it in the Magento configuration ("Algolia Search > Indexing Manager" section)
 */
class Product implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected ProductBatchQueueProcessor $productBatchQueueProcessor
    ) {}

    /**
     * @throws NoSuchEntityException
     */
    public function execute($productIds)
    {
        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->productBatchQueueProcessor->processBatch($storeId, $productIds);
        }
    }

    public function executeFull()
    {
        if (!$this->configHelper->isProductsIndexerEnabled()) {
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
        $this->execute([$id]);
    }
}
