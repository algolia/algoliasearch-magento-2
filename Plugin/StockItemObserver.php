<?php

namespace Algolia\AlgoliaSearch\Plugin;

use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\Model\AbstractModel as StockItem;

class StockItemObserver
{
    /** @var IndexerRegistry */
    protected $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    /**
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
     * @return \Magento\CatalogInventory\Model\ResourceModel\Stock\Item
     */
    public function afterDelete(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemResource,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $result,
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
