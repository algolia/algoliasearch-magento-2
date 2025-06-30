<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Api\Builder\UpdatableIndexBuilderInterface;
use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Exception\ProductReindexingException;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Helper\Entity\ProductHelper;
use Algolia\AlgoliaSearch\Helper\ProductDataArray;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Algolia\AlgoliaSearch\Service\AbstractIndexBuilder;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\Product\RecordBuilder as ProductRecordBuilder;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Config\ScopeCodeResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Store\Model\App\Emulation;

class IndexBuilder extends AbstractIndexBuilder implements UpdatableIndexBuilderInterface
{
    protected IndexerInterface $priceIndexer;

    public function __construct(
        protected ConfigHelper             $configHelper,
        protected DiagnosticsLogger        $logger,
        protected Emulation                $emulation,
        protected ScopeCodeResolver        $scopeCodeResolver,
        protected AlgoliaConnector         $algoliaConnector,
        protected IndexOptionsBuilder      $indexOptionsBuilder,
        protected ProductHelper            $productHelper,
        protected ProductRecordBuilder     $productRecordBuilder,
        protected ResourceConnection       $resource,
        protected ManagerInterface         $eventManager,
        protected MissingPriceIndexHandler $missingPriceIndexHandler,
        IndexerRegistry                    $indexerRegistry
    ){
        parent::__construct(
            $configHelper,
            $logger,
            $emulation,
            $scopeCodeResolver,
            $algoliaConnector
        );

        $this->priceIndexer = $indexerRegistry->get('catalog_product_price');
    }

    /**
     * @param int $storeId
     * @param array|null $options
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildIndexFull(int $storeId, array $options = null): void
    {
        $this->buildIndex($storeId, null, $options);
    }

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @param array|null $options
     * @return void
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    public function buildIndexList(int $storeId, array $entityIds = null, array $options = null): void
    {
        $this->buildIndex($storeId, $entityIds, $options);
    }

    /**
     * @param int $storeId
     * @param array|null $entityIds
     * @param array|null $options
     * @return void
     * @throws NoSuchEntityException
     * @throws \Exception
     */
    public function buildIndex(int $storeId, ?array $entityIds, ?array $options): void
    {
        if (!$this->isIndexingEnabled($storeId)) {
            return;
        }

        $this->startEmulation($storeId);

        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
        $collection = $this->productHelper->getProductCollectionQuery($storeId, $entityIds, $onlyVisible);

        $this->buildIndexPage(
            $storeId,
            $collection,
            $options['page'] ?? 1,
            $options['pageSize'] ?? $this->configHelper->getNumberOfElementByPage($storeId),
            $entityIds
        );

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
        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId);
        $client = $this->algoliaConnector->getClient($storeId);
        $objectIds = [];
        $counter = 0;
        $browseOptions = [
            'query'                => '',
            'attributesToRetrieve' => [AlgoliaConnector::ALGOLIA_API_OBJECT_ID],
        ];
        $hits = $client->browseObjects($indexOptions->getIndexName(), $browseOptions);
        foreach ($hits as $hit) {
            $objectIds[] = $hit[AlgoliaConnector::ALGOLIA_API_OBJECT_ID];
            $counter++;
            if ($counter === 1000) {
                $this->deleteInactiveIds($storeId, $objectIds, $indexOptions);
                $objectIds = [];
                $counter = 0;
            }
        }
        if (!empty($objectIds)) {
            $this->deleteInactiveIds($storeId, $objectIds, $indexOptions);
        }
    }

    /**
     * @param int $storeId
     * @param Collection $collection - collection to be paged
     * @param int $page
     * @param int $pageSize
     * @param array|null $productIds - pre-batched product ids - if specified no paging will be applied
     * @return void
     * @throws AlgoliaException
     * @throws DiagnosticsException
     * @throws NoSuchEntityException
     */
    protected function buildIndexPage(
        int $storeId,
        Collection $collection,
        int $page,
        int $pageSize,
        ?array $productIds = null
    ): void
    {
        if ($this->isIndexingEnabled($storeId) === false) {
            return;
        }

        $wrapperLogMessage = 'Build products index page: ' . $this->logger->getStoreName($storeId) . ',
            page ' . $page . ',
            pageSize ' . $pageSize;
        $this->logger->start($wrapperLogMessage, true);

        $additionalAttributes = $this->configHelper->getProductAdditionalAttributes($storeId);

        if (empty($productIds)) {
            $collection->setCurPage($page)->setPageSize($pageSize);
        }

        $collection->addCategoryIds();
        $collection->addUrlRewrite();

        if ($this->productRecordBuilder->isAttributeEnabled($additionalAttributes, 'rating_summary')) {
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

        if ($this->configHelper->isAutoPriceIndexingEnabled($storeId)) {
            $this->missingPriceIndexHandler->refreshPriceIndex($collection);
        }

        $logMessage = 'LOADING: ' . $this->logger->getStoreName($storeId) . ',
            collection page: ' . $page . ',
            pageSize: ' . $pageSize;
        $this->logger->start($logMessage);
        $collection->load(); // eliminate extra query to obtain count
        $this->logger->log('Loaded ' . count($collection) . ' products');
        $this->logger->stop($logMessage);
        $indexOptions = $this->indexOptionsBuilder->buildEntityIndexOptions($storeId, $useTmpIndex);
        $indexData = $this->getProductsRecords($storeId, $collection, $productIds);
        if (!empty($indexData['toIndex'])) {
            $this->logger->start('ADD/UPDATE TO ALGOLIA');
            $this->saveObjects($indexData['toIndex'], $indexOptions);
            $this->logger->log('Product IDs: ' . implode(', ', array_keys($indexData['toIndex'])));
            $this->logger->stop('ADD/UPDATE TO ALGOLIA');
        }

        if (!empty($indexData['toRemove'])) {
            $toRealRemove = $this->getIdsToRealRemove($indexOptions, $indexData['toRemove']);
            if (!empty($toRealRemove)) {
                $this->logger->start('REMOVE FROM ALGOLIA');
                $this->algoliaConnector->deleteObjects($toRealRemove, $indexOptions);
                $this->logger->log('Product IDs: ' . implode(', ', $toRealRemove));
                $this->logger->stop('REMOVE FROM ALGOLIA');
            }
        }
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
                $this->logger->startProfiling("canProductBeReindexed");
                $this->productRecordBuilder->canProductBeReindexed($product, $storeId);
            } catch (ProductReindexingException $e) {
                $productsToRemove[$productId] = $productId;
                continue;
            } finally {
                $this->logger->stopProfiling("canProductBeReindexed");
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

            $productsToIndex[$productId] = $this->productRecordBuilder->buildRecord($product);
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
        if ($this->productRecordBuilder->isAttributeEnabled($additionalAttributes, 'ordered_qty') === false
            && $this->productRecordBuilder->isAttributeEnabled($additionalAttributes, 'total_ordered') === false) {
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
     * @param $indexOptions
     * @return void
     * @throws AlgoliaException
     */
    protected function deleteInactiveIds($storeId, $objectIds, $indexOptions): void
    {
        $onlyVisible = !$this->configHelper->includeNonVisibleProductsInIndex($storeId);
        $collection = $this->productHelper->getProductCollectionQuery($storeId, $objectIds, $onlyVisible);
        $dbIds = $collection->getAllIds();
        $collection = null;
        $idsToDeleteFromAlgolia = array_diff($objectIds, $dbIds);
        $this->algoliaConnector->deleteObjects($idsToDeleteFromAlgolia, $indexOptions);
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
