<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product;

use Algolia\AlgoliaSearch\Service\Product\BatchQueueProcessor as ProductBatchQueueProcessor;
use Algolia\AlgoliaSearch\Test\Integration\Indexing\IndexingTestCase;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductsIndexingTestCase extends IndexingTestCase
{

    protected ?StockRegistry $stockRegistry = null;
    protected ?ProductBatchQueueProcessor $productBatchQueueProcessor = null;
    protected ?IndexerRegistry $indexerRegistry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productBatchQueueProcessor = $this->objectManager->get(ProductBatchQueueProcessor::class);
        $this->stockRegistry = $this->objectManager->get(StockRegistry::class);
        $this->indexerRegistry = $this->objectManager->get(IndexerRegistry::class);

        $this->indexerRegistry->get('catalog_product_price')->reindexAll();
    }

    /**
     * @throws NoSuchEntityException
     */
    protected function updateStockItem(string $sku, bool $isInStock): void
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsInStock($isInStock);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
    }
}
