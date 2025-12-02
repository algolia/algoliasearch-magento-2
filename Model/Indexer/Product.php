<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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
        protected BatchQueueProcessorInterface $productBatchQueueProcessor
    ) {}

    /**
     * {@inheritdoc}
     */
    public function execute($ids): void
    {
        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->productBatchQueueProcessor->processBatch($storeId, $ids);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function executeFull(): void
    {
        if (!$this->configHelper->isProductsIndexerEnabled()) {
            return;
        }

        $this->execute([]);
    }

    /**
     * {@inheritdoc}
     */
    public function executeList(array $ids): void
    {
        $this->execute($ids);
    }

    /**
     * {@inheritdoc}
     */
    public function executeRow($id): void
    {
        $this->execute([$id]);
    }
}
