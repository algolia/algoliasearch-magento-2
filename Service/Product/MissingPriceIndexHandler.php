<?php

namespace Algolia\AlgoliaSearch\Service\Product;

use Magento\Framework\DB\Select;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Indexer\IndexerInterface;
use Zend_Db_Select;

class MissingPriceIndexHandler
{
    public const PRICE_INDEX_TABLE = 'catalog_product_index_price';
    public const PRICE_INDEX_TABLE_ALIAS = 'price_index';
    public const MAIN_TABLE_ALIAS = 'e';

    protected IndexerInterface $indexer;
    public function __construct(
        protected CollectionFactory $productCollectionFactory,
        IndexerRegistry $indexerRegistry
    )
    {
        $this->indexer = $indexerRegistry->get('catalog_product_price');
    }

    /**
     * @param string[]|ProductCollection $products
     * @return string[] Array of product IDs that were reindexed by this repair operation
     */
    public function refreshPriceIndex(array|ProductCollection $products): array
    {
        $reindexIds = $this->getProductIdsToReindex($products);
        if (empty($reindexIds)) {
            return [];
        }

        $this->indexer->reindexList($reindexIds);

        return $reindexIds;
    }

    /**
     * @param string[]|ProductCollection $products
     * @return string[]
     */
    protected function getProductIdsToReindex(array|ProductCollection $products): array
    {
        $productIds = $products instanceof ProductCollection
            ? $this->getExpandedProductCollectionIds($products)
            : $products;

        $state = $this->indexer->getState()->getStatus();
        if ($state === \Magento\Framework\Indexer\StateInterface::STATUS_INVALID) {
            return $productIds;
        }

        return $this->filterProductIds($productIds);
    }

    protected function filterProductIds(array $productIds): array
    {
        $collection = $this->productCollectionFactory->create();

        $collection->addAttributeToSelect(['name', 'price']);

        $collection->getSelect()->joinLeft(
            ['price_index' => 'catalog_product_index_price'],
            'e.entity_id = price_index.entity_id',
            []
        );

        $collection->getSelect()
            ->where('price_index.entity_id IS NULL')
            ->where('e.entity_id IN (?)', $productIds);

        return $collection->getAllIds();
    }

    protected function getExpandedProductCollectionIds(ProductCollection $collection): array
    {
        $expandedCollection = clone $collection;

        $select = $expandedCollection->getSelect();

        $joins = $select->getPart(Zend_Db_Select::FROM);

        $priceIndexJoin = $this->getPriceIndexJoinAlias($joins);

        if (!$priceIndexJoin) {
            // nothing to do here - keep calm and carry on
            return [];
        }

        $modifyJoin = &$joins[$priceIndexJoin];
        $modifyJoin['joinType'] = Zend_Db_Select::LEFT_JOIN;

        $this->rebuildJoins($select, $joins);

        return $expandedCollection->getAllIds();
    }

    protected function rebuildJoins(Select $select, array $joins): void
    {
        $select->reset(Zend_Db_Select::FROM);
        foreach ($joins as $alias => $joinData) {
            if ($joinData['joinType'] === Zend_Db_Select::FROM) {
                $select->from([$alias => $joinData['tableName']]);
            } elseif ($joinData['joinType'] === Zend_Db_Select::LEFT_JOIN) {
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
            $sql = $select->__toString();
        }
    }

    private function inspectJoins(array $joins): void {
        foreach ($joins as $alias => $joinData) {
            echo "Table Alias: $alias\n";
            echo "Table Name: {$joinData['tableName']}\n";
            echo "Join Type: {$joinData['joinType']}\n";
            echo "Join Condition: " . ($joinData['joinCondition'] ?? 'N/A') . "\n\n";
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
