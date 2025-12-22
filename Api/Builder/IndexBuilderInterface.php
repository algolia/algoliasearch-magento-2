<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

interface IndexBuilderInterface
{
    public const BUILD_INDEX_METHOD = 'buildIndex';
    public const BUILD_INDEX_FULL_METHOD = 'buildIndexFull';

    public function buildIndex(int $storeId, ?array $entityIds, ?array $options): void;

    public function buildIndexFull(int $storeId, ?array $options): void;
}
