<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Helper\Entity\AdditionalSectionHelper;
use Algolia\AlgoliaSearch\Helper\Entity\CategoryHelper;
use Algolia\AlgoliaSearch\Helper\Entity\PageHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\Entity\SuggestionHelper;
use AlgoliaSearch\AlgoliaException;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Search\Model\Query;
use Magento\Store\Model\App\Emulation;

class Data
{
    const COLLECTION_PAGE_SIZE = 100;

    protected $algoliaHelper;

    protected $pageHelper;
    protected $categoryHelper;
    protected $productHelper;
    protected $additionalSectionHelper;
    protected $stockRegistry;

    protected $logger;
    protected $configHelper;
    protected $emulation;
    protected $resource;
    protected $eventManager;

    private $emulationRuns = false;

    public function __construct(AlgoliaHelper $algoliaHelper,
        ConfigHelper $configHelper,
        ProductHelper $producthelper,
        CategoryHelper $categoryHelper,
        PageHelper $pageHelper,
        SuggestionHelper $suggestionHelper,
        AdditionalSectionHelper $additionalSectionHelper,
        StockRegistryInterface $stockRegistry,
        Emulation $emulation,
        Logger $logger,
        ResourceConnection $resource,
        ManagerInterface $eventManager)
    {
        $this->algoliaHelper = $algoliaHelper;

        $this->pageHelper = $pageHelper;
        $this->categoryHelper = $categoryHelper;
        $this->productHelper = $producthelper;
        $this->suggestionHelper = $suggestionHelper;
        $this->additionalSectionHelper = $additionalSectionHelper;
        $this->stockRegistry = $stockRegistry;

        $this->configHelper = $configHelper;
        $this->logger = $logger;
        $this->emulation = $emulation;
        $this->resource = $resource;
        $this->eventManager = $eventManager;
    }

    public function deleteObjects($storeId, $ids, $indexName)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->deleteObjects($ids, $indexName);
    }

    public function saveConfigurationToAlgolia($storeId, $useTmpIndex = false)
    {
        if (!($this->configHelper->getApplicationID() && $this->configHelper->getAPIKey())) {
            return;
        }

        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setSettings($this->categoryHelper->getIndexName($storeId), $this->categoryHelper->getIndexSettings($storeId));

        $this->algoliaHelper->setSettings($this->pageHelper->getIndexName($storeId), $this->pageHelper->getIndexSettings($storeId));
        $this->algoliaHelper->setSettings($this->suggestionHelper->getIndexName($storeId), $this->suggestionHelper->getIndexSettings($storeId));

        foreach ($this->configHelper->getAutocompleteSections() as $section) {
            if ($section['name'] === 'products' || $section['name'] === 'categories' || $section['name'] === 'pages' || $section['name'] === 'suggestions') {
                continue;
            }

            $this->algoliaHelper->setSettings($this->additionalSectionHelper->getIndexName($storeId) . '_' . $section['name'], $this->additionalSectionHelper->getIndexSettings($storeId));
        }

        $this->productHelper->setSettings($storeId, $useTmpIndex);

        $this->setExtraSettings($storeId, $useTmpIndex);
    }

    public function getSearchResult($query, $storeId)
    {
        $indexName = $this->productHelper->getIndexName($storeId);

        $numberOfResults = 1000;
        if ($this->configHelper->isInstantEnabled()) {
            $numberOfResults = min($this->configHelper->getNumberOfProductResults($storeId), 1000);
        }

        $answer = $this->algoliaHelper->query($indexName, $query, [
            'hitsPerPage'            => $numberOfResults, // retrieve all the hits (hard limit is 1000)
            'attributesToRetrieve'   => 'objectID',
            'attributesToHighlight'  => '',
            'attributesToSnippet'    => '',
            'numericFilters'         => 'visibility_search=1',
            'removeWordsIfNoResults' => $this->configHelper->getRemoveWordsIfNoResult($storeId),
            'analyticsTags'          => 'backend-search',
        ]);

        $data = [];

        foreach ($answer['hits'] as $i => $hit) {
            $productId = $hit['objectID'];

            if ($productId) {
                $data[$productId] = [
                    'entity_id' => $productId,
                    'score'     => $numberOfResults - $i,
                ];
            }
        }

        return $data;
    }

    public function rebuildStoreAdditionalSectionsIndex($storeId)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $additional_sections = $this->configHelper->getAutocompleteSections();

        foreach ($additional_sections as $section) {
            if ($section['name'] === 'products' || $section['name'] === 'categories' || $section['name'] === 'pages' || $section['name'] === 'suggestions') {
                continue;
            }

            $index_name = $this->additionalSectionHelper->getIndexName($storeId) . '_' . $section['name'];

            $attribute_values = $this->additionalSectionHelper->getAttributeValues($storeId, $section);

            foreach (array_chunk($attribute_values, 100) as $chunk) {
                $this->algoliaHelper->addObjects($chunk, $index_name . '_tmp');
            }

            $this->algoliaHelper->moveIndex($index_name . '_tmp', $index_name);

            $this->algoliaHelper->setSettings($index_name, $this->additionalSectionHelper->getIndexSettings($storeId));
        }
    }

    public function rebuildStorePageIndex($storeId)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);

        $index_name = $this->pageHelper->getIndexName($storeId);

        $pages = $this->pageHelper->getPages($storeId);

        foreach (array_chunk($pages, 100) as $chunk) {
            $this->algoliaHelper->addObjects($chunk, $index_name . '_tmp');
        }

        $this->algoliaHelper->moveIndex($index_name . '_tmp', $index_name);

        $this->algoliaHelper->setSettings($index_name, $this->pageHelper->getIndexSettings($storeId));

        $this->stopEmulation();
    }

    public function rebuildStoreCategoryIndex($storeId, $categoryIds = null)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);

        try {
            $collection = $this->categoryHelper->getCategoryCollectionQuery($storeId, $categoryIds);

            $size = $collection->getSize();

            if ($size > 0) {
                $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
                $collection->clear();
                $page = 1;

                while ($page <= $pages) {
                    $this->rebuildStoreCategoryIndexPage($storeId, $collection, $page, $this->configHelper->getNumberOfElementByPage());

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

    public function rebuildStoreSuggestionIndex($storeId)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $collection = $this->suggestionHelper->getSuggestionCollectionQuery($storeId);

        $size = $collection->getSize();

        if ($size > 0) {
            $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
            $collection->clear();
            $page = 1;

            while ($page <= $pages) {
                $this->rebuildStoreSuggestionIndexPage($storeId, $collection, $page, $this->configHelper->getNumberOfElementByPage());

                $page++;
            }

            unset($indexData);
        }

        $this->moveStoreSuggestionIndex($storeId);
    }

    public function moveIndex($tmpIndexName, $indexName)
    {
        if ($this->isIndexingEnabled() === false) {
            return;
        }

        $this->algoliaHelper->moveIndex($tmpIndexName, $indexName);
    }

    public function moveStoreSuggestionIndex($storeId)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->moveIndex($this->suggestionHelper->getIndexName($storeId) . '_tmp', $this->suggestionHelper->getIndexName($storeId));
    }

    public function rebuildStoreProductIndex($storeId, $productIds)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->startEmulation($storeId);

        $this->logger->start('Indexing');
        try {
            $this->logger->start('ok');

            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds);
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
                    $this->rebuildStoreProductIndexPage($storeId, $collection, $page, $this->configHelper->getNumberOfElementByPage(), null, $productIds);

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

    public function rebuildProductIndex($storeId, $productIds, $page, $pageSize, $useTmpIndex)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $collection = $this->productHelper->getProductCollectionQuery($storeId, null, $useTmpIndex);
        $this->rebuildStoreProductIndexPage($storeId, $collection, $page, $pageSize, null, $productIds, $useTmpIndex);
    }

    public function rebuildStoreSuggestionIndexPage($storeId, $collectionDefault, $page, $pageSize)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        /** @var \Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection $collection */
        $collection = clone $collectionDefault;
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();

        $index_name = $this->suggestionHelper->getIndexName($storeId) . '_tmp';

        $indexData = [];

        /** @var Query $suggestion */
        foreach ($collection as $suggestion) {
            $suggestion->setStoreId($storeId);

            $suggestion_obj = $this->suggestionHelper->getObject($suggestion);

            if (strlen($suggestion_obj['query']) >= 3) {
                array_push($indexData, $suggestion_obj);
            }
        }

        if (count($indexData) > 0) {
            $this->algoliaHelper->addObjects($indexData, $index_name);
        }

        unset($indexData);

        $collection->walk('clearInstance');
        $collection->clear();

        unset($collection);
    }

    public function rebuildStoreCategoryIndexPage($storeId, $collectionDefault, $page, $pageSize)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        /** @var \Magento\Catalog\Model\ResourceModel\Category\Collection $collection */
        $collection = clone $collectionDefault;
        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->load();

        $index_name = $this->categoryHelper->getIndexName($storeId);

        $indexData = [];

        /** @var Category $category */
        foreach ($collection as $category) {
            if (!$this->categoryHelper->isCategoryActive($category->getId(), $storeId)) {
                continue;
            }

            $category->setStoreId($storeId);

            $category_obj = $this->categoryHelper->getObject($category);

            if ($category_obj['product_count'] > 0) {
                array_push($indexData, $category_obj);
            }
        }

        if (count($indexData) > 0) {
            $this->algoliaHelper->addObjects($indexData, $index_name);
        }

        unset($indexData);

        $collection->walk('clearInstance');
        $collection->clear();

        unset($collection);
    }

    protected function getProductsRecords($storeId, $collection, $potentiallyDeletedProductsIds = null)
    {
        $productsToIndex = [];
        $productsToRemove = [];

        // In $potentiallyDeletedProductsIds there might be IDs of deleted products which will not be in a collection
        if (is_array($potentiallyDeletedProductsIds)) {
            $potentiallyDeletedProductsIds = array_combine($potentiallyDeletedProductsIds, $potentiallyDeletedProductsIds);
        }

        $this->logger->start('CREATE RECORDS ' . $this->logger->getStoreName($storeId));
        $this->logger->log(count($collection) . ' product records to create');

        $salesData = $this->getSalesData($storeId, $collection);

        /** @var Product $product */
        foreach ($collection as $product) {
            $product->setStoreId($storeId);
            $product->setPriceCalculation(false);

            $productId = $product->getId();

            // If $productId is in the collection, remove it from $potentiallyDeletedProductsIds so it's not removed without check
            if (isset($potentiallyDeletedProductsIds[$productId])) {
                unset($potentiallyDeletedProductsIds[$productId]);
            }

            if (isset($productsToIndex[$productId]) || isset($productsToRemove[$productId])) {
                continue;
            }

            if ($product->isDeleted() === true
                || $product->getStatus() == Status::STATUS_DISABLED
                || !in_array((int) $product->getVisibility(), [Product\Visibility::VISIBILITY_BOTH, Product\Visibility::VISIBILITY_IN_SEARCH, Product\Visibility::VISIBILITY_IN_CATALOG], true)
            ) {
                $productsToRemove[$productId] = $productId;
                continue;
            }

            if (!$this->configHelper->getShowOutOfStock($storeId)) {
                $stockItem = $this->stockRegistry->getStockItem($product->getId());
                if (!$product->isSalable() || !$stockItem->getIsInStock()) {
                    $productsToRemove[$productId] = $productId;
                    continue;
                }
            }

            if (isset($salesData[$productId])) {
                $product->setData('ordered_qty', $salesData[$productId]['ordered_qty']);
                $product->setData('total_ordered', $salesData[$productId]['total_ordered']);
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

    public function rebuildStoreProductIndexPage($storeId, $collectionDefault, $page, $pageSize, $emulationInfo = null, $productIds = null, $useTmpIndex = false)
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->logger->start('rebuildStoreProductIndexPage ' . $this->logger->getStoreName($storeId) . ' page ' . $page . ' pageSize ' . $pageSize);

        if ($emulationInfo === null) {
            $this->startEmulation($storeId);
        }

        $superTableName = $this->resource->getTableName('catalog_product_super_link');
        $reviewTableName = $this->resource->getTableName('review_entity_summary');
        $stockTableName = $this->resource->getTableName('cataloginventory_stock_item');

        $additionalAttributes = $this->configHelper->getProductAdditionalAttributes($storeId);

        /** @var Collection $collection */
        $collection = clone $collectionDefault;

        $collection->setCurPage($page)->setPageSize($pageSize);
        $collection->addCategoryIds();
        $collection->addUrlRewrite();

        if ($this->productHelper->isAttributeEnabled($additionalAttributes, 'stock_qty')) {
            $collection->getSelect()->columns('(SELECT MAX(qty) FROM ' . $stockTableName . ' AS o LEFT JOIN ' . $superTableName . ' AS l ON l.product_id = o.product_id WHERE o.product_id = e.entity_id OR l.parent_id = e.entity_id) as stock_qty');
        }

        if ($this->productHelper->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
            $collection->getSelect()->columns('(SELECT MAX(rating_summary) FROM ' . $reviewTableName . ' AS o WHERE o.entity_pk_value = e.entity_id AND o.store_id = '.$storeId.') as rating_summary');
        }

        $this->eventManager->dispatch(
            'algolia_before_products_collection_load',
            ['collection' => $collection, 'store' => $storeId]
        );

        $this->logger->start('LOADING ' . $this->logger->getStoreName($storeId) . ' collection page ' . $page . ', pageSize ' . $pageSize);

        $collection->load();

        $this->logger->log('Loaded ' . count($collection) . ' products');
        $this->logger->stop('LOADING ' . $this->logger->getStoreName($storeId) . ' collection page ' . $page . ', pageSize ' . $pageSize);

        $indexName = $this->productHelper->getIndexName($storeId, $useTmpIndex);

        $indexData = $this->getProductsRecords($storeId, $collection, $productIds);

        if (!empty($indexData['toIndex'])) {
            $this->logger->start('ADD/UPDATE TO ALGOLIA');

            $this->algoliaHelper->addObjects($indexData['toIndex'], $indexName);

            $this->logger->log('Product IDs: ' . implode(', ', array_keys($indexData['toIndex'])));
            $this->logger->stop('ADD/UPDATE TO ALGOLIA');
        }

        if (!empty($indexData['toRemove'])) {
            $toRealRemove = [];

            if (count($indexData['toRemove']) === 1) {
                $toRealRemove = $indexData['toRemove'];
            } else {
                $indexData['toRemove'] = array_map('strval', $indexData['toRemove']);

                foreach (array_chunk($indexData['toRemove'], 1000) as $chunk) {
                    $objects = $this->algoliaHelper->getObjects($indexName, $chunk);
                    foreach ($objects['results'] as $object) {
                        if (isset($object['objectID'])) {
                            $toRealRemove[] = $object['objectID'];
                        }
                    }
                }
            }

            if (!empty($toRealRemove)) {
                $this->logger->start('REMOVE FROM ALGOLIA');

                $this->algoliaHelper->deleteObjects($toRealRemove, $indexName);

                $this->logger->log('Product IDs: '.implode(', ', $toRealRemove));
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

        $this->logger->stop('rebuildStoreProductIndexPage ' . $this->logger->getStoreName($storeId) . ' page ' . $page . ' pageSize ' . $pageSize);
    }

    public function startEmulation($storeId)
    {
        if ($this->emulationRuns === true) {
            return;
        }

        $this->logger->start('START EMULATION');

        $this->emulation->startEnvironmentEmulation($storeId);
        $this->emulationRuns = true;

        $this->logger->stop('START EMULATION');
    }

    public function stopEmulation()
    {
        $this->logger->start('STOP EMULATION');

        $this->emulation->stopEnvironmentEmulation();
        $this->emulationRuns = false;

        $this->logger->stop('STOP EMULATION');
    }

    private function setExtraSettings($storeId, $saveToTmpIndicesToo)
    {
        $sections = [
            'products' => $this->productHelper->getIndexName($storeId),
            'categories' => $this->categoryHelper->getIndexName($storeId),
            'pages' => $this->pageHelper->getIndexName($storeId),
            'suggestions' => $this->suggestionHelper->getIndexName($storeId),
            'additional_sections' => $this->additionalSectionHelper->getIndexName($storeId),
        ];

        $error = [];
        foreach ($sections as $section => $indexName) {
            try {
                $extraSettings = $this->configHelper->getExtraSettings($section, $storeId);

                if ($extraSettings) {
                    $extraSettings = json_decode($extraSettings, true);

                    $this->algoliaHelper->setSettings($indexName, $extraSettings, true);

                    if ($section === 'products' && $saveToTmpIndicesToo === true) {
                        $this->algoliaHelper->setSettings($indexName.'_tmp', $extraSettings, true);
                    }
                }
            } catch (AlgoliaException $e) {
                if (strpos($e->getMessage(), 'Invalid object attributes:') === 0) {
                    $error[] = 'Extra settings for "'.$section.'" indices were not saved. Error message: "'.$e->getMessage().'"';
                    continue;
                }

                throw $e;
            }
        }

        if (!empty($error)) {
            throw new AlgoliaException('<br>'.implode('<br> ', $error));
        }
    }

    private function isIndexingEnabled($storeId = null)
    {
        if ($this->configHelper->isEnabledBackend($storeId) === false) {
            $this->logger->log('INDEXING IS DISABLED FOR ' . $this->logger->getStoreName($storeId));

            return false;
        }

        return true;
    }

    private function getSalesData($storeId, Collection $collection)
    {
        $additionalAttributes = $this->configHelper->getProductAdditionalAttributes($storeId);
        if ($this->productHelper->isAttributeEnabled($additionalAttributes, 'ordered_qty') === false && $this->productHelper->isAttributeEnabled($additionalAttributes, 'total_ordered') === false) {
            return [];
        }

        $ordersTableName = $this->resource->getTableName('sales_order_item');

        $ids = $collection->getAllIds();
        $ids[] = '0'; // Makes sure the imploded string is not empty

        $ids = implode(', ', $ids);

        try {
            $salesConnection = $this->resource->getConnectionByName('sales');
        } catch(\DomainException $e) {
            $salesConnection = $this->resource->getConnection();
        }

        $query = 'SELECT product_id, SUM(qty_ordered) AS ordered_qty, SUM(row_total) AS total_ordered FROM ' . $ordersTableName . ' WHERE product_id IN (' . $ids . ') GROUP BY product_id';
        $salesData = $salesConnection->query($query)->fetchAll(\PDO::FETCH_GROUP|\PDO::FETCH_UNIQUE|\PDO::FETCH_ASSOC);

        return $salesData;
    }
}
