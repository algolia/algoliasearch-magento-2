<?php

namespace Algolia\AlgoliaSearch\Api\Console;

interface ReplicaDeleteCommandInterface
{
    public function deleteReplicas(array $storeIds = [], bool $unused = false): void;
    public function deleteReplicasForStore(int $storeId, bool $unused = false): void;
    public function deleteReplicasForAllStores(bool $unused = false): void;
}
