<?php

namespace Algolia\AlgoliaSearch\Model\Data\Queue;

/**
 * Canonical definition of a queue job: what runs and which store it is scoped to.
 *
 * storeId is the single source of truth for the job's store: it is persisted in the
 * queue's store_id column and injected into the handler by parameter name ($storeId)
 * at execution time. It never enters the serialized payload (JobDefinition::$data holds
 * the handler arguments excluding the store).
 */
class JobDefinition
{
    public function __construct(
        public readonly string $className,
        public readonly string $method,
        public readonly int $storeId,
        public readonly array $data = [],
    ) {
    }
}
