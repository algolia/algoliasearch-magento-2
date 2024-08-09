<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;

class IndexNameFetcher
{
    public function __construct(
        protected ConfigHelper          $configHelper,
        protected StoreManagerInterface $storeManager
    )
    {}

    /** @var string */
    public const INDEX_TEMP_SUFFIX = '_tmp';

    public const INDEX_QUERY_SUGGESTIONS_SUFFIX = '_query_suggestions';

    /**
     * @param string $indexSuffix
     * @param int|null $storeId
     * @param bool $tmp
     * @return string
     * @throws NoSuchEntityException
     */
    public function getIndexName(string $indexSuffix, ?int $storeId = null, bool $tmp = false): string
    {
        return $this->getBaseIndexName($storeId) . $indexSuffix . ($tmp ? self::INDEX_TEMP_SUFFIX : '');
    }

    /**
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseIndexName(?int $storeId = null): string
    {
        return $this->configHelper->getIndexPrefix($storeId) . $this->storeManager->getStore($storeId)->getCode();
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getProductIndexName(int $storeId, bool $tmp = false): string
    {
        return $this->getIndexName(ProductHelper::INDEX_NAME_SUFFIX, $storeId, $tmp);
    }

    public function isTempIndex($indexName): bool
    {
        return str_ends_with($indexName, self::INDEX_TEMP_SUFFIX);
    }

    /**
     * This is the default index name format for query suggestions but it can be overridden
     * This is a temporary workaround for delete index operations
     * TODO: Revisit this approach when a QuerySuggestionsClient is implemented in algoliasearch-client-php
     *
     * @param $indexName
     * @return bool
     */
    public function isQuerySuggestionsIndex($indexName): bool
    {
        return str_ends_with($indexName, self::INDEX_QUERY_SUGGESTIONS_SUFFIX);
    }

}
