<?php

namespace Algolia\AlgoliaSearch\Exception;

use Magento\Framework\Exception\LocalizedException;

class ReplicaLimitExceededException extends LocalizedException
{
    protected int $replicaCount = 0;

    public function withReplicaCount(int $replicaCount): ReplicaLimitExceededException
    {
        $this->replicaCount = $replicaCount;
        return $this;
    }

    public function getReplicaCount(): int {
        return $this->replicaCount;
    }

}
