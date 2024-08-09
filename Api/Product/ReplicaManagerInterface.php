<?php

namespace Algolia\AlgoliaSearch\Api\Product;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

interface ReplicaManagerInterface
{
    public const SORT_ATTRIBUTE_PRICE = 'price';

    public const SORT_KEY_ATTRIBUTE_NAME = 'attribute';
    public const SORT_KEY_VIRTUAL_REPLICA = 'virtualReplica';
    public const MAX_VIRTUAL_REPLICA_LIMIT = 20;

    /**
     * Configure replicas in Algolia based on the sorting configuration in Magento
     *
     * @param int $storeId
     * @param array<string, mixed> $primaryIndexSettings
     * @return void
     *
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws LocalizedException
     */
    public function syncReplicasToAlgolia(int $storeId, array $primaryIndexSettings): void;

    /**
     * Delete the replica indices on a store index
     * @param int $storeId
     * @param bool $unused Defaults to false - if true identifies any straggler indices and deletes those, otherwise deletes the replicas it knows aobut
     * @return void
     *
     * @throws LocalizedException
     * @throws AlgoliaException
     */
    public function deleteReplicasFromAlgolia(int $storeId, bool $unused = false): void;

    /**
     * For standard Magento front end (e.g. Luma) replicas will likely only be needed if InstantSearch is enabled
     * Headless implementations may wish to override this behavior via plugin
     * @param int $storeId
     * @return bool
     */
    public function isReplicaSyncEnabled(int $storeId): bool;

    /**
     * Return the number of virtual replicas permitted per index
     * @link https://www.algolia.com/doc/guides/managing-results/refine-results/sorting/in-depth/replicas/#differences
     *
     * @return int
     */
    public function getMaxVirtualReplicasPerIndex() : int;

    /**
     * For a given store return replicas that do not appear to be managed by Magento
     * @param int $storeId
     * @return string[]
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws AlgoliaException
     */
    public function getUnusedReplicaIndices(int $storeId): array;
}
