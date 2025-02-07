<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

interface UpdatableIndexBuilderInterface extends IndexBuilderInterface
{
    public function buildIndexList(int $storeId, ?array $entityIds, ?array $options): void;
}
