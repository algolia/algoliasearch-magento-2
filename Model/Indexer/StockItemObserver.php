<?php

namespace Algolia\AlgoliaSearch\Model\Indexer;

use Magento\Framework\Indexer\IndexerRegistry;

class StockItemObserver
{
    private $indexer;

    public function __construct(IndexerRegistry $indexerRegistry)
    {
        $this->indexer = $indexerRegistry->get('algolia_products');
    }

    public function afterSave(
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $stockItemModel,
        \Magento\CatalogInventory\Model\ResourceModel\Stock\Item $result,
        \Magento\CatalogInventory\Api\Data\StockItemInterface $stockItem
    ) {
        $stockItemModel->addCommitCallback(function () use ($stockItem) {
            if (!$this->indexer->isScheduled()) {
                $this->indexer->reindexRow($stockItem->getProductId());
            }
        });

        return $result;
    }

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
