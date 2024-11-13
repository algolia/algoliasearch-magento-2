<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;

class ProductsIndexingTestCase extends IndexingTestCase
{

    protected ?StockRegistry $stockRegistry = null;
    protected ?ProductIndexer $productIndexer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->productIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->stockRegistry = $this->objectManager->get(StockRegistry::class);

        $this->objectManager
            ->get(IndexerRegistry::class)
            ->get('catalog_product_price')
            ->reindexAll();
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
