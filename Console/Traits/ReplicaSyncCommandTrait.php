<?php

namespace Algolia\AlgoliaSearch\Console\Traits;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\BadRequestException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait ReplicaSyncCommandTrait
{

    /**
     * @param int[] $storeIds
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function syncReplicas(array $storeIds = []): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->syncReplicasForStore($storeId);
            }
        } else {
            $this->syncReplicasForAllStores();
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws LocalizedException
     */
    public function syncReplicasForStore(int $storeId): void
    {
        $this->output->writeln('<info>Syncing ' . $this->storeNameFetcher->getStoreName($storeId) . '...</info>');
        try {
            $this->replicaManager->syncReplicasToAlgolia($storeId, $this->productHelper->getIndexSettings($storeId));
        } catch (BadRequestException $e) {
            $this->output->writeln('<error>Failed syncing replicas for store "' . $this->storeNameFetcher->getStoreName($storeId) . '": ' . $e->getMessage() . '</error>');
            throw $e;
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     * @throws LocalizedException
     */
    public function syncReplicasForAllStores(): void
    {
        $storeIds = array_keys($this->storeManager->getStores());
        foreach ($storeIds as $storeId) {
            $this->syncReplicasForStore($storeId);
        }
    }
}
