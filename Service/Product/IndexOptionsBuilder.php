<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Service\AbstractEntityIndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends AbstractEntityIndexOptionsBuilder
{
    /**
     * @throws NoSuchEntityException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        return $this->safeBuildWithComputedIndex(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
    }
}
