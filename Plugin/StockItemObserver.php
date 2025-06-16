<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel as StockItem;

class StockItemObserver
{
    /**
     * @var IndexerRegistry
     */
    protected $indexer;

    /**
     * @param IndexerRegistry $indexerRegistry
     */
    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    /**
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemModel
     * @param StockItem $stockItem
     * @return void
     */
    public function beforeSave(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemModel,
        StockItem $stockItem
    ) {
        $stockItemModel->addCommitCallback(function () use ($stockItem) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($stockItem->getProductId());
            }
        });
    }

    /**
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemResource
     * @param \Magento\CatalogInventory\Model\ResourceModel\Stock\Item|null $result
     * @param \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
     * @return \Magento\CatalogInventory\Model\ResourceModel\Stock\Item|null
     */
    public function afterDelete(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemResource,
        ?\Magento\CatalogInventory\Model\ResourceModel\Stock\Item $result = null,
        \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
    ) {
        $stockItemResource->addCommitCallback(function () use ($stockItem) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($stockItem->getProductId());
            }
        });

        return $result;
    }
}
