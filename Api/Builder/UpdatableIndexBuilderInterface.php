<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

interface UpdatableIndexBuilderInterface extends IndexBuilderInterface
{
    public const BUILD_INDEX_LIST_METHOD = 'buildIndexList';
    public function buildIndexList(int $storeId, ?array $entityIds, ?array $options): void;
}
