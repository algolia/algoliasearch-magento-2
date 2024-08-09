<?php

namespace Algolia\AlgoliaSearch\Console\Traits;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

trait ReplicaDeleteCommandTrait
{
    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     * @throws LocalizedException
     */
    public function deleteReplicas(array $storeIds = [], bool $unused = false): void
    {
        if (count($storeIds)) {
            foreach ($storeIds as $storeId) {
                $this->deleteReplicasForStore($storeId, $unused);
            }
        } else {
            $this->deleteReplicasForAllStores($unused);
        }
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws AlgoliaException
     */
    public function deleteReplicasForStore(int $storeId, bool $unused = false): void
    {
        $this->output->writeln('<info>Deleting' . ($unused ? ' unused ' : ' ') . 'replicas for ' . $this->storeNameFetcher->getStoreName($storeId) . '...</info>');
        $this->replicaManager->deleteReplicasFromAlgolia($storeId, $unused);
    }

    /**
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws AlgoliaException
     */
    public function deleteReplicasForAllStores(bool $unused = false): void
    {
        $storeIds = array_keys($this->storeManager->getStores());
        foreach ($storeIds as $storeId) {
            $this->deleteReplicasForStore($storeId, $unused);
        }
    }
}
