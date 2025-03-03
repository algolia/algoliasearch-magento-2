<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

interface QueueBuilderInterface
{
    public function buildQueue(int $storeId, ?array $entityIds = null): void;
}
