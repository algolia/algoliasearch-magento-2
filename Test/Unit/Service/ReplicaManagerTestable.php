<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Service\Product\ReplicaManager;

class ReplicaManagerTestable extends ReplicaManager
{
    public function removeReplicaFromReplicaSetting(...$params): array
    {
        return parent::removeReplicaFromReplicaSetting(...$params);
    }
}
