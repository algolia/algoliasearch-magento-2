<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder as AdditionalSectionIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Category\IndexBuilder as CategoryIndexBuilder;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Algolia\AlgoliaSearch\Service\Page\IndexBuilder as PageIndexBuilder;
use Algolia\AlgoliaSearch\Service\Product\BackendSearch;
use Algolia\AlgoliaSearch\Service\Product\IndexBuilder as ProductIndexBuilder;
use Algolia\AlgoliaSearch\Service\Suggestion\IndexBuilder as SuggestionIndexBuilder;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;

class Data
{
    public function __construct(
        protected ConfigHelper                  $configHelper,
        protected DiagnosticsLogger             $logger,
        protected StoreManagerInterface         $storeManager,
        protected IndexNameFetcher              $indexNameFetcher,
        protected CategoryIndexBuilder          $categoryIndexBuilder,
        protected ProductIndexBuilder           $productIndexBuilder,
        protected AdditionalSectionIndexBuilder $additionalSectionIndexBuilder,
        protected PageIndexBuilder              $pageIndexBuilder,
        protected SuggestionIndexBuilder        $suggestionIndexBuilder,
        protected BackendSearch                 $backendSearch
    ){}

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     * @throws \Exception
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\AdditionalSection\IndexBuilder::buildIndexFull() instead
     */
    public function rebuildStoreAdditionalSectionsIndex(int $storeId): void
    {
        $this->additionalSectionIndexBuilder->buildIndexFull($storeId);
    }

    /**
     * @param $storeId
     * @param array|null $pageIds
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Page\IndexBuilder::buildIndexFull() instead
     */
    public function rebuildStorePageIndex($storeId, array $pageIds = null): void
    {
        $this->pageIndexBuilder->buildIndexFull($storeId, ['entityIds' => $pageIds]);
    }

    /**
     * @param $storeId
     * @param null $categoryIds
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Category\IndexBuilder::buildIndexList() instead
     */
    public function rebuildStoreCategoryIndex($storeId, $categoryIds = null): void
    {
        $this->categoryIndexBuilder->buildIndexList($storeId, $categoryIds);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Suggestion:\IndexBuilder:buildIndexFull() instead
     */
    public function rebuildStoreSuggestionIndex(int $storeId): void
    {
        $this->suggestionIndexBuilder->buildIndexFull($storeId);
    }

    /**
     * @param int $storeId
     * @param string[] $productIds
     * @return void
     * @throws \Exception
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Product\IndexBuilder::buildIndexList() instead
     */
    public function rebuildStoreProductIndex(int $storeId, array $productIds): void
    {
        $this->productIndexBuilder->buildIndexList($storeId, $productIds);
    }

    /**
     * @param int $storeId
     * @param array|null $productIds
     * @param int $page
     * @param int $pageSize
     * @param bool $useTmpIndex
     * @return void
     * @throws \Exception
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Product\IndexBuilder::buildIndexFull() instead
     */
    public function rebuildProductIndex(int $storeId, ?array $productIds, int $page, int $pageSize, bool $useTmpIndex): void
    {
        $this->productIndexBuilder->buildIndexFull(
            $storeId,
            [
                'productIds' => $productIds,
                'page' => $page,
                'pageSize' => $pageSize,
                'useTmpIndex' => $useTmpIndex
            ]
        );
    }

    /**
     * @param int $storeId
     * @param int $page
     * @param int $pageSize
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Category\IndexBuilder::buildIndexFull() instead
     */
    public function rebuildCategoryIndex(int $storeId, int $page, int $pageSize): void
    {
        $this->categoryIndexBuilder->buildIndexFull($storeId, ['page' => $page, 'pageSize' => $pageSize]);
    }

    /**
     * @param $storeId
     * @return void
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Product\IndexBuilder::deleteInactiveProducts() instead
     */
    public function deleteInactiveProducts($storeId): void
    {
        $this->productIndexBuilder->deleteInactiveProducts($storeId);
    }

    /**
     * @param string $query
     * @param int $storeId
     * @param array|null $searchParams
     * @param string|null $targetedIndex
     * @return array
     * @throws AlgoliaException|NoSuchEntityException
     * @internal This method is currently unstable and should not be used. It may be revisited or fixed in a future version.
     *
     * @deprecated
     * Use Algolia\AlgoliaSearch\Service\Product\BackendSearch::getSearchResult() instead
     */
    public function getSearchResult(string $query, int $storeId, ?array $searchParams = null, ?string $targetedIndex = null): array
    {
        return $this->backendSearch->getSearchResult($query, $storeId, $searchParams, $targetedIndex);
    }

    /**
     * @param $storeId
     * @return bool
     * @throws NoSuchEntityException
     */
    public function isIndexingEnabled($storeId = null): bool
    {
        if ($this->configHelper->isEnabledBackend($storeId) === false) {
            $this->logger->log('INDEXING IS DISABLED FOR ' . $this->logger->getStoreName($storeId));
            return false;
        }
        return true;
    }

    /**
     * @param string $indexSuffix
     * @param int|null $storeId
     * @param bool $tmp
     * @return string
     * @throws NoSuchEntityException
     */
    public function getIndexName(string $indexSuffix, int $storeId = null, bool $tmp = false): string
    {
        return $this->indexNameFetcher->getIndexName($indexSuffix, $storeId, $tmp);
    }

    /**
     * @param int|null $storeId
     * @return string
     * @throws NoSuchEntityException
     */
    public function getBaseIndexName(int $storeId = null): string
    {
        return $this->indexNameFetcher->getBaseIndexName($storeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     * @throws NoSuchEntityException
     */
    public function getIndexDataByStoreIds(): array
    {
        $indexNames = [];
        $indexNames[AlgoliaConnector::ALGOLIA_DEFAULT_SCOPE] = $this->buildIndexData();
        foreach ($this->storeManager->getStores() as $store) {
            $indexNames[$store->getId()] = $this->buildIndexData($store);
        }
        return $indexNames;
    }

    /**
     * @param StoreInterface|null $store
     * @return array
     * @throws NoSuchEntityException
     */
    protected function buildIndexData(StoreInterface $store = null): array
    {
        $storeId = !is_null($store) ? $store->getStoreId() : null;
        $currencyCode = !is_null($store) ?
            $store->getCurrentCurrencyCode($storeId) :
            $this->configHelper->getCurrencyCode();

        return [
            'appId' => $this->configHelper->getApplicationID($storeId),
            'apiKey' => $this->configHelper->getAPIKey($storeId),
            'indexName' => $this->getBaseIndexName($storeId),
            'priceKey' => '.' . $currencyCode . '.default',
            'facets' => $this->configHelper->getFacets($storeId),
            'currencyCode' => $this->configHelper->getCurrencyCode($storeId),
            'maxValuesPerFacet' => (int) $this->configHelper->getMaxValuesPerFacet($storeId),
            'categorySeparator' => $this->configHelper->getCategorySeparator($storeId),
        ];
    }
}
