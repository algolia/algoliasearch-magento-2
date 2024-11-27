<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder as SuggestionIndexBuilder;
use Magento\Store\Model\StoreManagerInterface;

class Suggestion implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected SuggestionHelper $suggestionHelper,
        protected Data $dataHelper,
        protected AlgoliaHelper $algoliaHelper,
        protected Queue $queue,
        protected ConfigHelper $configHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute($ids)
    {
    }

    public function executeFull()
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

                return;
            }

            /** @uses SuggestionIndexBuilder::buildIndex() */
            $this->queue->addToQueue(
                SuggestionIndexBuilder::class,
                'buildIndex',
                ['storeId' => $storeId],
                1
            );
        }
    }

    public function executeList(array $ids)
    {
    }

    public function executeRow($id)
    {
    }
}
