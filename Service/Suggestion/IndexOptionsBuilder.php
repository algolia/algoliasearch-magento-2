<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Builder\EntityIndexOptionsBuilderInterface;
use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder as BaseIndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends BaseIndexOptionsBuilder implements EntityIndexOptionsBuilderInterface
{
    /**
     * @throws NoSuchEntityException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        return $this->buildWithComputedIndex(SuggestionHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
    }
}
