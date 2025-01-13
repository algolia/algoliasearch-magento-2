<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Algolia\AlgoliaSearch\Logger\DiagnosticsLogger;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\Framework\Indexer\IndexerInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\StateInterface;

class MissingPriceIndexHandler
{
    public const PRICE_INDEX_TABLE = 'catalog_product_index_price';
    public const PRICE_INDEX_TABLE_ALIAS = 'price_index';
    public const MAIN_TABLE_ALIAS = 'e';

    protected array $_indexedProducts = [];

    protected IndexerInterface $indexer;
    public function __construct(
        protected CollectionFactory $productCollectionFactory,
        protected ResourceConnection $resourceConnection,
        protected DiagnosticsLogger $diagnostics,
        IndexerRegistry $indexerRegistry
    )
    {
        $this->indexer = $indexerRegistry->get('catalog_product_price');
    }

    /**
     * @param string[]|ProductCollection $products
     * @return string[] Array of product IDs that were reindexed by this repair operation
     * @throws DiagnosticsException
     */
    public function refreshPriceIndex(array|ProductCollection $products): array
    {
        $this->diagnostics->startProfiling(__METHOD__);
        $reindexIds = $this->getProductIdsToReindex($products);
        if (empty($reindexIds)) {
            $this->diagnostics->stopProfiling(__METHOD__);
            return [];
        }

        $this->diagnostics->log(__("Pricing records missing or invalid for %1 product(s)", count($reindexIds)));
        $this->diagnostics->log(__("Reindexing product ID(s): %1", implode(', ', $reindexIds)));

        $this->indexer->reindexList($reindexIds);

        $this->diagnostics->stopProfiling(__METHOD__);
        return $reindexIds;
    }

    /**
     * Analyzes a product collection and determines which (if any) records should have their prices reindexed
     * @param string[]|ProductCollection $products - either an explicit list of product ids or a product collection
     * @return string[] IDs of products that require price reindexing (will be empty if no indexing is required)
     */
    protected function getProductIdsToReindex(array|ProductCollection $products): array
    {
        $productIds = $products instanceof ProductCollection
            ? $this->getProductIdsFromCollection($products)
            : $products;

        if (empty($productIds)) {
            return [];
        }

        $state = $this->indexer->getState()->getStatus();
        if ($state === StateInterface::STATUS_INVALID) {
            return $this->filterProductIdsNotYetProcessed($productIds);
        }

        $productIds = $this->filterProductIdsMissingPricing($productIds);
        if (empty($productIds)) {
            return [];
        }

        return $this->filterProductIdsNotYetProcessed($productIds);
    }

    protected function filterProductIdsMissingPricing(array $productIds): array
    {
        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect(['name', 'price']);

        $collection->getSelect()->joinLeft(
            [self::PRICE_INDEX_TABLE_ALIAS => self::PRICE_INDEX_TABLE],
            self::MAIN_TABLE_ALIAS . '.entity_id = ' . self::PRICE_INDEX_TABLE_ALIAS . '.entity_id',
            []
        );

        $collection->getSelect()
            ->where(self::PRICE_INDEX_TABLE_ALIAS . '.entity_id IS NULL')
            ->where(self::MAIN_TABLE_ALIAS . '.entity_id IN (?)', $productIds);

        return $collection->getAllIds();
    }

    protected function filterProductIdsNotYetProcessed(array $productIds): array {
        $pendingProcessing = array_fill_keys($productIds, true);

        $notProcessed = array_diff_key($pendingProcessing, $this->_indexedProducts);

        if (empty($notProcessed)) {
            return [];
        }

        $this->_indexedProducts += $notProcessed;

        return array_keys($notProcessed);
    }

    /**
     * Expand the query for product ids from the collection regardless of price index status
     * @return string[] An array of indices to be evaluated - array will be empty if no price index join found
     */
    protected function getProductIdsFromCollection(ProductCollection $collection): array
    {

        $select = clone $collection->getSelect();
        try {
            $joins = $select->getPart(Select::FROM);
        } catch (\Zend_Db_Select_Exception $e) {
            $this->diagnostics->error("Unable to build query for missing product prices: " . $e->getMessage());
            return [];
        }

        $priceIndexJoin = $this->getPriceIndexJoinAlias($joins);

        if (!$priceIndexJoin) {
            // no price index on query - keep calm and carry on
            return [];
        }

        $this->expandPricingJoin($joins, $priceIndexJoin);
        $this->rebuildJoins($select, $joins);

        return $this->resourceConnection->getConnection()->fetchCol($select);
    }

    protected function expandPricingJoin(array &$joins, string $priceIndexJoin): void
    {
        $modifyJoin = &$joins[$priceIndexJoin];
        $modifyJoin['joinType'] = Select::LEFT_JOIN;
    }

    protected function rebuildJoins(Select $select, array $joins): void
    {
        $select->reset(Select::COLUMNS);
        $select->reset(Select::FROM);
        foreach ($joins as $alias => $joinData) {
            if ($joinData['joinType'] === Select::FROM) {
                $select->from(
                    [$alias => $joinData['tableName']],
                    'entity_id'
                );
            } elseif ($joinData['joinType'] === Select::LEFT_JOIN) {
                $select->joinLeft(
                    [$alias => $joinData['tableName']],
                    $joinData['joinCondition'],
                    [],
                    $joinData['schema']
                );
            } else {
                $select->join(
                    [$alias => $joinData['tableName']],
                    $joinData['joinCondition'],
                    [],
                    $joinData['schema']
                );
            }
        }
    }

    /**
     * @param array<string, array> $joins
     * @return string
     */
    protected function getPriceIndexJoinAlias(array $joins): string
    {
        if (isset($joins[self::PRICE_INDEX_TABLE_ALIAS])) {
            return self::PRICE_INDEX_TABLE_ALIAS;
        }
        else {
            foreach ($joins as $alias => $joinData) {
                if ($joinData['tableName'] === self::PRICE_INDEX_TABLE) {
                    return $alias;
                }
            }
        }

        return "";
    }
}
