<?php

namespace Algolia\AlgoliaSearch\Api;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;

interface SendStrategyInterface
{
    /**
     * Determine if this strategy should handle send operations for the given store.
     * Only called on optional strategies - not the default fallback.
     *
     * @param int $storeId
     * @return bool
     */
    public function isApplicable(int $storeId): bool;

    /**
     * @param IndexOptionsInterface $indexOptions
     * @param array $requests Batch requests [{action, body}, ...]
     * @return array Response from the send operation
     */
    public function send(IndexOptionsInterface $indexOptions, array $requests): array;
}
