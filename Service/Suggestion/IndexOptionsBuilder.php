<?php

namespace Algolia\AlgoliaSearch\Service\Suggestion;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Service\AbstractEntityIndexOptionsBuilder;
use Magento\Framework\Exception\NoSuchEntityException;

class IndexOptionsBuilder extends AbstractEntityIndexOptionsBuilder
{
    /**
     * @throws NoSuchEntityException
     */
    public function buildEntityIndexOptions(int $storeId, ?bool $isTmp = false): IndexOptionsInterface
    {
        return $this->safeBuildWithComputedIndex(SuggestionHelper::INDEX_NAME_SUFFIX, $storeId, $isTmp);
    }
}
