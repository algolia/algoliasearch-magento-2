<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\AlgoliaHelper;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\ProductDataArray;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder
{
    protected IndexerInterface $priceIndexer;

    public function __construct(
        protected ConfigHelper       $configHelper,
        protected DiagnosticsLogger  $logger,
        protected Emulation          $emulation,
        protected ScopeCodeResolver  $scopeCodeResolver,
        protected AlgoliaHelper      $algoliaHelper,
        protected ProductHelper      $productHelper,
        protected ResourceConnection $resource,
        protected ManagerInterface   $eventManager,
        IndexerRegistry              $indexerRegistry
    ){
        parent::__construct($configHelper, $logger, $emulation, $scopeCodeResolver, $algoliaHelper);

        $this->priceIndexer = $indexerRegistry->get('catalog_product_price');
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
    public function buildIndex(int $storeId, ?array $productIds, int $page, int $pageSize, bool $useTmpIndex): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
        $collection = $this->productHelper->getProductCollectionQuery($storeId, null, $onlyVisible);
        $this->buildIndexPage($storeId, $collection, $page, $pageSize, null, $productIds, $useTmpIndex);
    }

    /**
     * @param int $storeId
     * @param string[] $productIds
     * @return void
     * @throws \Exception
     */
    public function rebuildEntityIds(int $storeId, array $productIds): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->checkPriceIndex($productIds);

        $this->startEmulation($storeId);
        $this->logger->start('Indexing');
        try {
            $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
            $collection = $this->productHelper->getProductCollectionQuery($storeId, $productIds, $onlyVisible);
            $timerName = __METHOD__ . ' (Get product collection size)';
            $this->logger->startProfiling($timerName);
            $size = $collection->getSize();
            $this->logger->stopProfiling($timerName);

            if (!empty($productIds)) {
                $size = max(count($productIds), $size);
            }
            $this->logger->log('Store ' . $this->logger->getStoreName($storeId) . ' collection size : ' . $size);
            if ($size > 0) {
                $pages = ceil($size / $this->configHelper->getNumberOfElementByPage());
                $collection->clear();
                $page = 1;
                while ($page <= $pages) {
                    $this->buildIndexPage(
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
    protected function buildIndexPage(
        $storeId,
        $collectionDefault,
        $page,
        $pageSize,
        $emulationInfo = null,
        $productIds = null,
        $useTmpIndex = false
    ): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $this->algoliaHelper->setStoreId($storeId);

        $wrapperLogMessage = 'rebuildStoreProductIndexPage: ' . $this->logger->getStoreName($storeId) . ',
            page ' . $page . ',
            pageSize ' . $pageSize;
        $this->logger->start($wrapperLogMessage, true);
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

        $this->logger->stop($wrapperLogMessage, true);
    }

    /**
     * @param $storeId
     * @param $collection
     * @param $potentiallyDeletedProductsIds
     * @return array
     * @throws \Exception
     */
    protected function getProductsRecords($storeId, $collection, $potentiallyDeletedProductsIds = null): array
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

        $logEventName = 'CREATE RECORDS ' . $this->logger->getStoreName($storeId);
        $this->logger->start($logEventName, true);
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

        /** @var \Magento\Catalog\Model\Product $product */
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

        $this->logger->stop($logEventName, true);
        return [
            'toIndex' => $productsToIndex,
            'toRemove' => array_unique($productsToRemove),
        ];
    }

    /**
     * @param $storeId
     * @param Collection $collection
     * @return array
     */
    protected function getSalesData($storeId, Collection $collection): array
    {
        $this->logger->startProfiling(__METHOD__);
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
        $this->logger->stopProfiling(__METHOD__);
        return $salesData;
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
