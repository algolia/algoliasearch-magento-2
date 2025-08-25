<?php

namespace Algolia\AlgoliaSearch\Api\Processor;

interface BatchQueueProcessorInterface
{
    public function processBatch(int $storeId, ?array $entityIds = null): void;
}
