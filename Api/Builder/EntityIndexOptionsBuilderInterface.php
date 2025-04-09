<?php

namespace Algolia\AlgoliaSearch\Api\Builder;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;

interface EntityIndexOptionsBuilderInterface
{
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface;
}
