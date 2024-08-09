<?php

namespace Algolia\AlgoliaSearch\Api\Console;

interface ReplicaSyncCommandInterface
{
    public function syncReplicas(array $storeIds = []): void;
    public function syncReplicasForStore(int $storeId): void;
    public function syncReplicasForAllStores(): void;
}
