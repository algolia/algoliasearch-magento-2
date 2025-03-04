<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\Suggestion\QueueBuilder as SuggestionQueueBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Suggestion implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected ConfigHelper $configHelper,
        protected SuggestionQueueBuilder $suggestionQueueBuilder
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
            $this->suggestionQueueBuilder->buildQueue($storeId);
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
