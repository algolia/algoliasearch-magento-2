<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Suggestion\BatchQueueProcessor as SuggestionBatchQueueProcessor;
use Magento\Store\Model\StoreManagerInterface;

/**
 * This indexer is now disabled by default, prefer use the `bin/magento algolia:reindex:suggestions` command instead
 * If you want to re-enable it, you can do it in the Magento configuration ("Algolia Search > Indexing Manager" section)
 */
class Suggestion implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected SuggestionBatchQueueProcessor $suggestionBatchQueueProcessor
    ) {}

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        if (!$this->configHelper->isSuggestionsIndexerEnabled()) {
            return;
        }

        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $this->suggestionBatchQueueProcessor->processBatch($storeId);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
