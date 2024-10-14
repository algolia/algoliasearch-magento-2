<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ProductsIndexingTest extends ProductsIndexingTestCase
{
    /** @var ProductIndexer */
    protected $productsIndexer;

    /*** @var IndexerRegistry */
    protected $indexerRegistry;

    protected $productPriceIndexer;

    protected $testProductId;

    const OUT_OF_STOCK_PRODUCT_SKU = '24-MB01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->productsIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->indexerRegistry = $this->objectManager->get(IndexerRegistry::class);

        $this->productPriceIndexer = $this->indexerRegistry->get('catalog_product_price');
        $this->productPriceIndexer->reindexAll();
    }

    public function testOnlyOnStockProducts()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productsIndexer, 'products', $this->assertValues->productsOnStockCount);
    }

    public function testIncludingOutOfStock()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 1);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productsIndexer, 'products', $this->assertValues->productsOutOfStockCount);
    }

    public function testDefaultIndexableAttributes()
    {
        $empty = $this->getSerializer()->serialize([]);

        $this->setConfig(ConfigHelper::PRODUCT_ATTRIBUTES, $empty);
        $this->setConfig(ConfigHelper::FACETS, $empty);
        $this->setConfig(ConfigHelper::SORTING_INDICES, $empty);
        $this->setConfig(ConfigHelper::PRODUCT_CUSTOM_RANKING, $empty);

        $this->productsIndexer->executeRow($this->getValidTestProduct());
        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_products', [$this->getValidTestProduct()]);
        $hit = reset($results['results']);

        $defaultAttributes = [
            'objectID',
            'name',
            'url',
            'visibility_search',
            'visibility_catalog',
            'categories',
            'categories_without_path',
            'thumbnail_url',
            'image_url',
            'in_stock',
            'price',
            'type_id',
            'algoliaLastUpdateAtCET',
            'categoryIds',
        ];

        if (!$hit) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions on.');
        }

        foreach ($defaultAttributes as $key => $attribute) {
            $this->assertArrayHasKey($attribute, $hit, 'Products attribute "' . $attribute . '" should be indexed but it is not"');
            unset($hit[$attribute]);
        }

        $extraAttributes = implode(', ', array_keys($hit));
        $this->assertEmpty($hit, 'Extra products attributes (' . $extraAttributes . ') are indexed and should not be.');
    }

    private function getValidTestProduct()
    {
        if (!$this->testProductId) {
            /** @var Product $product */
            $product = $this->getObjectManager()->get(Product::class);
            $this->testProductId = $product->getIdBySku('MSH09');
        }

        return $this->testProductId;
    }

    /**
     * @throws NoSuchEntityException
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, true);
    }
}
