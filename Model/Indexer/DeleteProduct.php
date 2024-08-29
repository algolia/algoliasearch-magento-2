<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\Data;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Magento\Store\Model\StoreManagerInterface;

class DeleteProduct implements \Magento\Framework\Indexer\ActionInterface, \Magento\Framework\Mview\ActionInterface
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        protected Data $fullAction,
        protected AlgoliaHelper $algoliaHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager
    )
    {}

    public function execute($ids)
    {
        return $this;
    }

    public function executeFull()
    {
        $storeIds = array_keys($this->storeManager->getStores());

        foreach ($storeIds as $storeId) {
            if ($this->fullAction->isIndexingEnabled($storeId) === false) {
                continue;
            }

            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey($storeId)) {
                $errorMessage = 'Algolia reindexing failed for store :' . $storeId . '(DeleteProduct indexer)
                You need to configure your Algolia credentials in Stores > Configuration > Algolia Search.';

                $this->algoliaCredentialsManager->displayErrorMessage($errorMessage);

                return;
            }

            $this->fullAction->deleteInactiveProducts($storeId);
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
