<?php

namespace Algolia\AlgoliaSearch\Service\Page;

use Algolia\AlgoliaSearch\Api\Builder\EntityIndexOptionsBuilderInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder as BaseIndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends BaseIndexOptionsBuilder implements EntityIndexOptionsBuilderInterface
{
    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        return $this->buildWithComputedIndex(PageHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
    }
}
