<?php

namespace Algolia\AlgoliaSearch\Validator;

use Algolia\AlgoliaSearch\Api\Product\ReplicaManagerInterface;

class VirtualReplicaValidator
{
    protected int $replicaCount = 0;
    protected int $priceSortReplicaCount = 0;
    protected bool $replicaLimitExceeded = false;
    protected bool $tooManyCustomerGroups = false;

    public function __construct(
        protected ReplicaManagerInterface $replicaManager
    )
    {}

    public function isReplicaConfigurationValid(array $replicas): bool
    {
        foreach ($replicas as $replica) {
            $maxReplicas = $this->replicaManager->getMaxVirtualReplicasPerIndex();
            if (!empty($replica[ReplicaManagerInterface::SORT_KEY_VIRTUAL_REPLICA])) {
                $this->replicaCount++;
                if ($replica[ReplicaManagerInterface::SORT_KEY_ATTRIBUTE_NAME] == ReplicaManagerInterface::SORT_ATTRIBUTE_PRICE) {
                    $this->priceSortReplicaCount++;
                }
            }

            if ($this->replicaCount > $maxReplicas) {
                $this->replicaLimitExceeded = true;
                $this->tooManyCustomerGroups = $this->priceSortReplicaCount > $maxReplicas;
            }
        }
        return !$this->replicaLimitExceeded;
    }

    public function isTooManyCustomerGroups(): bool
    {
        return $this->tooManyCustomerGroups;
    }

    public function getReplicaCount(): int
    {
        return $this->replicaCount;
    }

    public function getPriceSortReplicaCount(): int
    {
        return $this->priceSortReplicaCount;
    }
}
