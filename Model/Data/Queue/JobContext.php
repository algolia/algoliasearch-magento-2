<?php

namespace Algolia\AlgoliaSearch\Model\Data\Queue;

/**
 * Queue treatment knobs for a job: how the queue batches and prioritizes it
 * (as opposed to JobDefinition, which describes what the job acts on).
 */
class JobContext
{
    public function __construct(
        public readonly int $dataSize = 1,
        public readonly bool $isFullReindex = false,
    ) {
    }
}
