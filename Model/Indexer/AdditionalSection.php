<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder as AdditionalSectionIndexBuilder;
use Magento\Store\Model\StoreManagerInterface;

class AdditionalSection implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected Data $dataHelper,
        protected Queue $queue,
        protected ConfigHelper $configHelper,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        if (!$this->configHelper->isAdditionalSectionsIndexerEnabled()) {
            return;
        }

        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->dataHelper->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class, $storeId);

                return;
            }

            /** @uses AdditionalSectionIndexBuilder::buildIndexFull() */
            $this->queue->addToQueue(
                AdditionalSectionIndexBuilder::class,
                'buildIndexFull',
                ['storeId' => $storeId],
                1
            );
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
