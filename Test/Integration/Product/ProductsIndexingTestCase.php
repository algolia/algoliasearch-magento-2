<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Exception\NoSuchEntityException;

class ProductsIndexingTestCase extends IndexingTestCase
{

    protected ?StockRegistry $stockRegistry = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->stockRegistry = $this->objectManager->get(StockRegistry::class);
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
