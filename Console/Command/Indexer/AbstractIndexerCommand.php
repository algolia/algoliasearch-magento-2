<?php

namespace Algolia\AlgoliaSearch\Console\Command\Indexer;

use Algolia\AlgoliaSearch\Console\Command\AbstractStoreCommand;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\App\State;
use Magento\Store\Model\StoreManagerInterface;

abstract class AbstractIndexerCommand extends AbstractStoreCommand
{
    public function __construct(
        protected StoreManagerInterface $storeManager,
        State $state,
        StoreNameFetcher $storeNameFetcher,
        ?string $name = null
    ) {
        parent::__construct($state, $storeNameFetcher, $name);
    }

    protected function getCommandPrefix(): string
    {
        return parent::getCommandPrefix() . 'reindex:';
    }

    protected function getStoreArgumentDescription(): string
    {
        return 'ID(s) for store(s) you want to reindex to Algolia (optional), if not specified, all stores will be reindexed';
    }

    protected function getStoreIdsToIndex($input): array
    {
        $storeIds = $this->getStoreIds($input);

        if (count($storeIds) === 0) {
            $storeIds = array_keys($this->storeManager->getStores());
        }

        return $storeIds;
    }
}
