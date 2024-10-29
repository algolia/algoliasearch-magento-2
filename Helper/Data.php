<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Exception\CategoryReindexingException;
use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use Algolia\AlgoliaSearch\Service\IndexNameFetcher;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Search\Model\Query;
use Magento\Search\Model\ResourceModel\Query\Collection as QueryCollection;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;

class Data
{
    protected bool $emulationRuns = false;

    protected IndexerInterface $priceIndexer;

    public function __construct(
        protected AlgoliaHelper           $algoliaHelper,
        protected ConfigHelper            $configHelper,
        protected ProductHelper           $productHelper,
        protected CategoryHelper          $categoryHelper,
        protected PageHelper              $pageHelper,
        protected SuggestionHelper        $suggestionHelper,
        protected AdditionalSectionHelper $additionalSectionHelper,
        protected Emulation               $emulation,
        protected Logger                  $logger,
        protected ResourceConnection      $resource,
        protected ManagerInterface        $eventManager,
        protected ScopeCodeResolver       $scopeCodeResolver,
        protected StoreManagerInterface   $storeManager,
        protected IndexNameFetcher        $indexNameFetcher,
        IndexerRegistry                   $indexerRegistry
    )
    {
        $this->priceIndexer = $indexerRegistry->get('catalog_product_price');
    }

    /**
     * @return ConfigHelper
     */
    public function getConfigHelper(): ConfigHelper
    {
        return $this->configHelper;
    }

    /**
     * @param int $storeId
     * @param array $ids
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects(int $storeId, array $ids, string $indexName): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $this->algoliaHelper->deleteObjects($ids, $indexName);
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
     */
    public function getSearchResult(string $query, int $storeId, ?array $searchParams = null, ?string $targetedIndex = null): array
    {
        $indexName = $targetedIndex !== null ?
            $targetedIndex :
            $this->productHelper->getIndexName($storeId);

        $numberOfResults = 1000;
        if ($this->configHelper->isInstantEnabled()) {
            $numberOfResults = min($this->configHelper->getNumberOfProductResults($storeId), 1000);
        }

        $facetsToRetrieve = [];
        foreach ($this->configHelper->getFacets($storeId) as $facet) {
            $facetsToRetrieve[] = $facet['attribute'];
        }

        $params = [
            'hitsPerPage'            => $numberOfResults, // retrieve all the hits (hard limit is 1000)
            'attributesToRetrieve'   => AlgoliaHelper::ALGOLIA_API_OBJECT_ID,
            'attributesToHighlight'  => '',
            'attributesToSnippet'    => '',
            'numericFilters'         => ['visibility_search=1'],
            'removeWordsIfNoResults' => $this->configHelper->getRemoveWordsIfNoResult($storeId),
            'analyticsTags'          => 'backend-search',
            'facets'                 => $facetsToRetrieve,
            'maxValuesPerFacet'      => 100,
        ];

        if (is_array($searchParams)) {
            $params = array_merge($params, $searchParams);
        }

        $response = $this->algoliaHelper->query($indexName, $query, $params);
        $answer = reset($response['results']);

        $data = [];

        foreach ($answer['hits'] as $i => $hit) {
            $productId = $hit[AlgoliaHelper::ALGOLIA_API_OBJECT_ID];

            if ($productId) {
                $data[$productId] = [
                    'entity_id' => $productId,
                    'score' => $numberOfResults - $i,
                ];
            }
        }

        $facetsFromAnswer = $answer['facets'] ?? [];

        return [$data, $answer['nbHits'], $facetsFromAnswer];
    }

    /**
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws \Exception
     */
    protected function saveObjects(array $objects, string $indexName): void {
        $this->algoliaHelper->saveObjects($indexName, $objects, $this->configHelper->isPartialUpdateEnabled());
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function rebuildStoreAdditionalSectionsIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $additionalSections = $this->configHelper->getAutocompleteSections();

        $protectedSections = ['products', 'categories', 'pages', 'suggestions'];
        foreach ($additionalSections as $section) {
            if (in_array($section['name'], $protectedSections, true)) {
                continue;
            }

            $indexName = $this->additionalSectionHelper->getIndexName($storeId);
            $indexName = $indexName . '_' . $section['name'];

            $attributeValues = $this->additionalSectionHelper->getAttributeValues($storeId, $section);

            $tempIndexName = $indexName . IndexNameFetcher::INDEX_TEMP_SUFFIX;

            foreach (array_chunk($attributeValues, 100) as $chunk) {
                $this->saveObjects($chunk, $tempIndexName);
            }

            $this->algoliaHelper->copyQueryRules($indexName, $tempIndexName);
            $this->algoliaHelper->moveIndex($tempIndexName, $indexName);

            $this->algoliaHelper->setSettings($indexName, $this->additionalSectionHelper->getIndexSettings($storeId));

            $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
        }
    }

    /**
     * @param $storeId
     * @param array|null $pageIds
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function rebuildStorePageIndex($storeId, array $pageIds = null): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        if (!$this->configHelper->isPagesIndexEnabled($storeId)) {
            $this->logger->log('Pages Indexing is not enabled for the store.');
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $indexName = $this->pageHelper->getIndexName($storeId);

        $this->startEmulation($storeId);

        $pages = $this->pageHelper->getPages($storeId, $pageIds);

        $this->stopEmulation();

        // if there are pageIds defined, do not index to _tmp
        $isFullReindex = (!$pageIds);

        if (isset($pages['toIndex']) && count($pages['toIndex'])) {
            $pagesToIndex = $pages['toIndex'];
            $toIndexName = $indexName . ($isFullReindex ? IndexNameFetcher::INDEX_TEMP_SUFFIX : '');

            foreach (array_chunk($pagesToIndex, 100) as $chunk) {
                try {
                    $this->saveObjects($chunk, $toIndexName);
                } catch (\Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }
        }

        if (!$isFullReindex && isset($pages['toRemove']) && count($pages['toRemove'])) {
            $pagesToRemove = $pages['toRemove'];
            foreach (array_chunk($pagesToRemove, 100) as $chunk) {
                try {
                    $this->algoliaHelper->deleteObjects($chunk, $indexName);
                } catch (\Exception $e) {
                    $this->logger->log($e->getMessage());
                    continue;
                }
            }
        }

        if ($isFullReindex) {
            $tempIndexName = $this->pageHelper->getTempIndexName($storeId);
            $this->algoliaHelper->copyQueryRules($indexName, $tempIndexName);
            $this->algoliaHelper->moveIndex($tempIndexName, $indexName);
        }
        $this->algoliaHelper->setSettings($indexName, $this->pageHelper->getIndexSettings($storeId));
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param $storeId
     * @param $categoryIds
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function rebuildStoreCategoryIndex($storeId, $categoryIds = null)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);

        try {
            $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId, $categoryIds);

            $size = $collection->getSize();
            if (!empty($categoryIds)) {
                $size = max(count($categoryIds), $size);
            }

            if ($size > 0) {
                $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
                $page = 1;
                while ($page <= $pages) {
                    $this->rebuildStoreCategoryIndexPage(
                        $storeId,
                        $collection,
                        $page,
                        $this->configHelper->getNumberOfElementByPage(),
                        $categoryIds
                    );
                    $page++;
                }
                unset($indexData);
            }
        } catch (\Exception $e) {
            $this->stopEmulation();
            throw $e;
        }
        $this->stopEmulation();
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     * @throws NoSuchEntityException
     */
    public function rebuildStoreSuggestionIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false || !$this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            return;
        }

        if (!$this->configHelper->isQuerySuggestionsIndexEnabled($storeId)) {
            $this->logger->log('Query Suggestions Indexing is not enabled for the store.');
            return;
        }

        $collection = $this->suggestionHelper->getSuggestionCollectionQuery($storeId);
        $size = $collection->getSize();

        if ($size > 0) {
            $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
            $collection->clear();
            $page = 1;

            while ($page <= $pages) {
                $this->rebuildStoreSuggestionIndexPage(
                    $storeId,
                    $collection,
                    $page,
                    $this->configHelper->getNumberOfElementByPage()
                );
                $page++;
            }
            unset($indexData);
        }
        $this->moveStoreSuggestionIndex($storeId);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     */
    public function moveStoreSuggestionIndex(int $storeId): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $this->algoliaHelper->setStoreId($storeId);
        $tmpIndexName = $this->suggestionHelper->getTempIndexName($storeId);
        $indexName = $this->suggestionHelper->getIndexName($storeId);
        $this->algoliaHelper->copyQueryRules($indexName, $tmpIndexName);
        $this->algoliaHelper->moveIndex($tmpIndexName, $indexName);
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param int $storeId
     * @param string[] $productIds
     * @return void
     * @throws \Exception
     */
    public function rebuildStoreProductIndex(int $storeId, array $productIds): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->checkPriceIndex($productIds);

        $this->startEmulation($storeId);
        $this->logger->start('Indexing');
        try {
            $this->logger->start('ok');
            $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds, $onlyVisible);
            $size = $collection->getSize();
            if (!empty($productIds)) {
                $size = max(count($productIds), $size);
            }
            $this->logger->log('Store ' . $this->logger->getStoreName($storeId) . ' collection size : ' . $size);
            if ($size > 0) {
                $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
                $collection->clear();
                $page = 1;
                while ($page <= $pages) {
                    $this->rebuildStoreProductIndexPage(
                        $storeId,
                        $collection,
                        $page,
                        $this->configHelper->getNumberOfElementByPage(),
                        null,
                        $productIds
                    );
                    $page++;
                }
            }
        } catch (\Exception $e) {
            $this->stopEmulation();
            throw $e;
        }
        $this->logger->stop('Indexing');
        $this->stopEmulation();
    }

    /**
     * @param int $storeId
     * @param array|null $productIds
     * @param int $page
     * @param int $pageSize
     * @param bool $useTmpIndex
     * @return void
     * @throws \Exception
     */
    public function rebuildProductIndex(int $storeId, ?array $productIds, int $page, int $pageSize, bool $useTmpIndex): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
        $collection = $this->productHelper->getProductCollectionQuery($storeId, null, $onlyVisible);
        $this->rebuildStoreProductIndexPage($storeId, $collection, $page, $pageSize, null, $productIds, $useTmpIndex);
    }

    /**
     * @param $storeId
     * @param $page
     * @param $pageSize
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function rebuildCategoryIndex(int $storeId, int $page, int $pageSize): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);
        $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId, null);
        $this->rebuildStoreCategoryIndexPage($storeId, $collection, $page, $pageSize);
        $this->stopEmulation();
    }

    /**
     * @param int $storeId
     * @param QueryCollection $collectionDefault
     * @param int $page
     * @param int $pageSize
     * @return void
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function rebuildStoreSuggestionIndexPage(int $storeId, QueryCollection $collectionDefault, int $page, int $pageSize): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);
        $collection = clone $collectionDefault;
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();
        $indexName = $this->suggestionHelper->getTempIndexName($storeId);
        $indexData = [];

        /** @var Query $suggestion */
        foreach ($collection as $suggestion) {
            $suggestion->setStoreId($storeId);
            $suggestionObject = $this->suggestionHelper->getObject($suggestion);
            if (mb_strlen($suggestionObject['query']) >= 3) {
                array_push($indexData, $suggestionObject);
            }
        }
        if (count($indexData) > 0) {
            $this->saveObjects($indexData, $indexName);
        }

        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
        unset($collection);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function rebuildStoreCategoryIndexPage($storeId, $collection, $page, $pageSize, $categoryIds = null): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $this->algoliaHelper->setStoreId($storeId);
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();
        $indexName = $this->categoryHelper->getIndexName($storeId);
        $indexData = $this->getCategoryRecords($storeId, $collection, $categoryIds);
        if (!empty($indexData['toIndex'])) {
            $this->logger->start('ADD/UPDATE TO ALGOLIA');
            $this->saveObjects($indexData['toIndex'], $indexName);
            $this->logger->log('Product IDs: ' . implode(', ', array_keys($indexData['toIndex'])));
            $this->logger->stop('ADD/UPDATE TO ALGOLIA');
        }

        if (!empty($indexData['toRemove'])) {
            $toRealRemove = $this->getIdsToRealRemove($indexName, $indexData['toRemove']);
            if (!empty($toRealRemove)) {
                $this->logger->start('REMOVE FROM ALGOLIA');
                $this->algoliaHelper->deleteObjects($toRealRemove, $indexName);
                $this->logger->log('Category IDs: ' . implode(', ', $toRealRemove));
                $this->logger->stop('REMOVE FROM ALGOLIA');
            }
        }
        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        unset($collection);
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param $storeId
     * @param $collection
     * @param $potentiallyDeletedProductsIds
     * @return array
     * @throws \Exception
     */
    protected function getProductsRecords($storeId, $collection, $potentiallyDeletedProductsIds = null)
    {
        $productsToIndex = [];
        $productsToRemove = [];

        // In $potentiallyDeletedProductsIds there might be IDs of deleted products which will not be in a collection
        if (is_array($potentiallyDeletedProductsIds)) {
            $potentiallyDeletedProductsIds = array_combine(
                $potentiallyDeletedProductsIds,
                $potentiallyDeletedProductsIds
            );
        }

        $this->logger->start('CREATE RECORDS ' . $this->logger->getStoreName($storeId));
        $this->logger->log(count($collection) . ' product records to create');
        $salesData = $this->getSalesData($storeId, $collection);
        $transport = new ProductDataArray();
        $this->eventManager->dispatch(
            'algolia_product_collection_add_additional_data',
            [
                'collection'      => $collection,
                'store_id'        => $storeId,
                'additional_data' => $transport
            ]
        );

        /** @var Product $product */
        foreach ($collection as $product) {
            $product->setStoreId($storeId);
            $product->setPriceCalculation(false);
            $productId = $product->getId();
            // If $productId is in the collection, remove it from $potentiallyDeletedProductsIds
            // so it's not removed without check
            if (isset($potentiallyDeletedProductsIds[$productId])) {
                unset($potentiallyDeletedProductsIds[$productId]);
            }

            if (isset($productsToIndex[$productId]) || isset($productsToRemove[$productId])) {
                continue;
            }

            try {
                $this->productHelper->canProductBeReindexed($product, $storeId);
            } catch (ProductReindexingException $e) {
                $productsToRemove[$productId] = $productId;
                continue;
            }

            if (isset($salesData[$productId])) {
                $product->setData('ordered_qty', $salesData[$productId]['ordered_qty']);
                $product->setData('total_ordered', $salesData[$productId]['total_ordered']);
            }

            if ($additionalData = $transport->getItem($productId)) {
                foreach ($additionalData as $key => $value) {
                    $product->setData($key, $value);
                }
            }

            $productsToIndex[$productId] = $this->productHelper->getObject($product);
        }

        if (is_array($potentiallyDeletedProductsIds)) {
            $productsToRemove = array_merge($productsToRemove, $potentiallyDeletedProductsIds);
        }

        $this->logger->stop('CREATE RECORDS ' . $this->logger->getStoreName($storeId));
        return [
            'toIndex' => $productsToIndex,
            'toRemove' => array_unique($productsToRemove),
        ];
    }

    /**
     * @param int $storeId
     * @param \Magento\Catalog\Model\ResourceModel\Category\Collection $collection
     * @param array|null $potentiallyDeletedCategoriesIds
     *
     * @return array
     * @throws NoSuchEntityException
     *
     */
    protected function getCategoryRecords($storeId, $collection, $potentiallyDeletedCategoriesIds = null)
    {
        $categoriesToIndex = [];
        $categoriesToRemove = [];

        // In $potentiallyDeletedCategoriesIds there might be IDs of deleted products which will not be in a collection
        if (is_array($potentiallyDeletedCategoriesIds)) {
            $potentiallyDeletedCategoriesIds = array_combine(
                $potentiallyDeletedCategoriesIds,
                $potentiallyDeletedCategoriesIds
            );
        }

        /** @var Category $category */
        foreach ($collection as $category) {
            $category->setStoreId($storeId);
            $categoryId = $category->getId();
            // If $categoryId is in the collection, remove it from $potentiallyDeletedProductsIds
            // so it's not removed without check
            if (isset($potentiallyDeletedCategoriesIds[$categoryId])) {
                unset($potentiallyDeletedCategoriesIds[$categoryId]);
            }

            if (isset($categoriesToIndex[$categoryId]) || isset($categoriesToRemove[$categoryId])) {
                continue;
            }

            try {
                $this->categoryHelper->canCategoryBeReindexed($category, $storeId);
            } catch (CategoryReindexingException $e) {
                $categoriesToRemove[$categoryId] = $categoryId;
                continue;
            }

            $categoriesToIndex[$categoryId] = $this->categoryHelper->getObject($category);
        }

        if (is_array($potentiallyDeletedCategoriesIds)) {
            $categoriesToRemove = array_merge($categoriesToRemove, $potentiallyDeletedCategoriesIds);
        }

        return [
            'toIndex'  => $categoriesToIndex,
            'toRemove' => array_unique($categoriesToRemove),
        ];
    }

    /**
     * @param $storeId
     * @param $collectionDefault
     * @param $page
     * @param $pageSize
     * @param $emulationInfo
     * @param $productIds
     * @param $useTmpIndex
     * @return void
     * @throws \Exception
     */
    public function rebuildStoreProductIndexPage(
        $storeId,
        $collectionDefault,
        $page,
        $pageSize,
        $emulationInfo = null,
        $productIds = null,
        $useTmpIndex = false
    )
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $wrapperLogMessage = 'rebuildStoreProductIndexPage: ' . $this->logger->getStoreName($storeId) . ',
            page ' . $page . ',
            pageSize ' . $pageSize;
        $this->logger->start($wrapperLogMessage);
        if ($emulationInfo === null) {
            $this->startEmulation($storeId);
        }
        $additionalAttributes = $this->configHelper->getProductAdditionalAttributes($storeId);

        /** @var Collection $collection */
        $collection = clone $collectionDefault;
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->addCategoryIds();
        $collection->addUrlRewrite();

        if ($this->productHelper->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
            $reviewTableName = $this->resource->getTableName('review_entity_summary');
            $collection
                ->getSelect()
                ->columns('(SELECT coalesce(MAX(rating_summary), 0) FROM ' . $reviewTableName . ' AS o WHERE o.entity_pk_value = e.entity_id AND o.store_id = ' . $storeId . ') as rating_summary');
        }

        $this->eventManager->dispatch(
            'algolia_before_products_collection_load',
            [
                'collection' => $collection,
                'store'      => $storeId
            ]
        );
        $logMessage = 'LOADING: ' . $this->logger->getStoreName($storeId) . ',
            collection page: ' . $page . ',
            pageSize: ' . $pageSize;
        $this->logger->start($logMessage);
        $collection->load();
        $this->logger->log('Loaded ' . count($collection) . ' products');
        $this->logger->stop($logMessage);
        $indexName = $this->productHelper->getIndexName($storeId, $useTmpIndex);
        $indexData = $this->getProductsRecords($storeId, $collection, $productIds);
        if (!empty($indexData['toIndex'])) {
            $this->logger->start('ADD/UPDATE TO ALGOLIA');
            $this->saveObjects($indexData['toIndex'], $indexName);
            $this->logger->log('Product IDs: ' . implode(', ', array_keys($indexData['toIndex'])));
            $this->logger->stop('ADD/UPDATE TO ALGOLIA');
        }

        if (!empty($indexData['toRemove'])) {
            $toRealRemove = $this->getIdsToRealRemove($indexName, $indexData['toRemove']);
            if (!empty($toRealRemove)) {
                $this->logger->start('REMOVE FROM ALGOLIA');
                $this->algoliaHelper->deleteObjects($toRealRemove, $indexName);
                $this->logger->log('Product IDs: ' . implode(', ', $toRealRemove));
                $this->logger->stop('REMOVE FROM ALGOLIA');
            }
        }
        unset($indexData);
        $collection->walk('clearInstance');
        $collection->clear();
        unset($collection);
        if ($emulationInfo === null) {
            $this->stopEmulation();
        }

        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);

        $this->logger->stop($wrapperLogMessage);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws \Exception
     */
    public function startEmulation(int $storeId): void
    {
        if ($this->emulationRuns === true) {
            return;
        }

        $this->logger->start('START EMULATION');
        $this->emulation->startEnvironmentEmulation($storeId, Area::AREA_FRONTEND, true);
        $this->scopeCodeResolver->clean();
        $this->emulationRuns = true;
        $this->logger->stop('START EMULATION');
    }

    /**
     * @return void
     * @throws \Exception
     */
    public function stopEmulation(): void
    {
        $this->logger->start('STOP EMULATION');
        $this->emulation->stopEnvironmentEmulation();
        $this->emulationRuns = false;
        $this->logger->stop('STOP EMULATION');
    }

    /**
     * @param $storeId
     * @return bool
     */
    public function isIndexingEnabled($storeId = null)
    {
        if ($this->configHelper->isEnabledBackend($storeId) === false) {
            $this->logger->log('INDEXING IS DISABLED FOR ' . $this->logger->getStoreName($storeId));
            return false;
        }
        return true;
    }

    /**
     * @param $indexName
     * @param $idsToRemove
     * @return array|mixed
     */
    protected function getIdsToRealRemove($indexName, $idsToRemove)
    {
        if (count($idsToRemove) === 1) {
            return $idsToRemove;
        }

        $toRealRemove = [];
        $idsToRemove = array_map('strval', $idsToRemove);
        foreach (array_chunk($idsToRemove, 1000) as $chunk) {
            $objects = $this->algoliaHelper->getObjects($indexName, $chunk);
            foreach ($objects['results'] as $object) {
                if (isset($object[AlgoliaHelper::ALGOLIA_API_OBJECT_ID])) {
                    $toRealRemove[] = $object[AlgoliaHelper::ALGOLIA_API_OBJECT_ID];
                }
            }
        }
        return $toRealRemove;
    }

    /**
     * @param $storeId
     * @param Collection $collection
     * @return array
     */
    protected function getSalesData($storeId, Collection $collection)
    {
        $additionalAttributes = $this->configHelper->getProductAdditionalAttributes($storeId);
        if ($this->productHelper->isAttributeEnabled($additionalAttributes, 'ordered_qty') === false
            && $this->productHelper->isAttributeEnabled($additionalAttributes, 'total_ordered') === false) {
            return [];
        }

        $salesData = [];
        $ids = $collection->getColumnValues('entity_id');
        if (count($ids)) {
            $ordersTableName = $this->resource->getTableName('sales_order_item');
            try {
                $salesConnection = $this->resource->getConnectionByName('sales');
            } catch (\DomainException $e) {
                $salesConnection = $this->resource->getConnection();
            }
            $select = $salesConnection->select()
                ->from($ordersTableName, [])
                ->columns('product_id')
                ->columns(['ordered_qty' => new \Zend_Db_Expr('SUM(qty_ordered)')])
                ->columns(['total_ordered' => new \Zend_Db_Expr('SUM(row_total)')])
                ->where('product_id IN (?)', $ids)
                ->group('product_id');
            $salesData = $salesConnection->fetchAll($select, [], \PDO::FETCH_GROUP | \PDO::FETCH_ASSOC | \PDO::FETCH_UNIQUE);
        }
        return $salesData;
    }

    /**
     * @param $storeId
     * @return void
     * @throws NoSuchEntityException
     * @throws AlgoliaException
     */
    public function deleteInactiveProducts($storeId): void
    {
        $indexName = $this->productHelper->getIndexName($storeId);
        $this->algoliaHelper->setStoreId($storeId);
        $client = $this->algoliaHelper->getClient();
        $objectIds = [];
        $counter = 0;
        $browseOptions = [
            'query'                => '',
            'attributesToRetrieve' => [AlgoliaHelper::ALGOLIA_API_OBJECT_ID],
        ];
        $hits = $client->browseObjects($indexName, $browseOptions);
        foreach ($hits as $hit) {
            $objectIds[] = $hit[AlgoliaHelper::ALGOLIA_API_OBJECT_ID];
            $counter++;
            if ($counter === 1000) {
                $this->deleteInactiveIds($storeId, $objectIds, $indexName);
                $objectIds = [];
                $counter = 0;
            }
        }
        if (!empty($objectIds)) {
            $this->deleteInactiveIds($storeId, $objectIds, $indexName);
        }
        $this->algoliaHelper->setStoreId(AlgoliaHelper::ALGOLIA_DEFAULT_SCOPE);
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
        $indexNames[0] = [
            'appId' => $this->configHelper->getApplicationID(),
            'apiKey' => $this->configHelper->getAPIKey(),
            'indexName' => $this->getBaseIndexName(),
            'priceKey'  => '.' . $this->configHelper->getCurrencyCode() . '.default',
        ];
        foreach ($this->storeManager->getStores() as $store) {
            $indexNames[$store->getId()] = [
                'appId' => $this->configHelper->getApplicationID($store->getId()),
                'apiKey' => $this->configHelper->getAPIKey($store->getId()),
                'indexName' => $this->getBaseIndexName($store->getId()),
                'priceKey' => '.' . $store->getCurrentCurrencyCode($store->getId()) . '.default',
            ];
        }
        return $indexNames;
    }

    /**
     * @param $storeId
     * @param $objectIds
     * @param $indexName
     * @return void
     * @throws AlgoliaException
     */
    protected function deleteInactiveIds($storeId, $objectIds, $indexName): void
    {
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
        $collection = $this->productHelper->getProductCollectionQuery($storeId, $objectIds, $onlyVisible);
        $dbIds = $collection->getAllIds();
        $collection = null;
        $idsToDeleteFromAlgolia = array_diff($objectIds, $dbIds);
        $this->algoliaHelper->deleteObjects($idsToDeleteFromAlgolia, $indexName);
    }

    /**
     * If the price index is stale
     * @param array $productIds
     * @return void
     */
    protected function checkPriceIndex(array $productIds): void
    {
        $state = $this->priceIndexer->getState()->getStatus();
        if ($state === \Magento\Framework\Indexer\StateInterface::STATUS_INVALID) {
            $this->priceIndexer->reindexList($productIds);
        }
    }

}
