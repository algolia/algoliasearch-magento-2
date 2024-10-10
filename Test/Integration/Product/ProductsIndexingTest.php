<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
use Algolia\AlgoliaSearch\Test\Integration\IndexingTestCase;
use Magento\Catalog\Model\Product;
use Magento\CatalogInventory\Model\StockRegistry;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ProductsIndexingTest extends IndexingTestCase
{
    /** @var ProductIndexer */
    protected $productsIndexer;

    /** @var StockRegistry */
    protected $stockRegistry;

    /*** @var IndexerRegistry */
    protected $indexerRegistry;

    protected $productPriceIndexer;

    protected $testProductId;

    const SPECIAL_PRICE_TEST_PRODUCT_ID = 9;

    const OUT_OF_STOCK_PRODUCT_SKU = '24-MB01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->productsIndexer = $this->objectManager->get(ProductIndexer::class);
        $this->stockRegistry = $this->objectManager->get(StockRegistry::class);
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

    public function testSpecialPrice()
    {
        $this->productsIndexer->execute([self::SPECIAL_PRICE_TEST_PRODUCT_ID]);
        $this->algoliaHelper->waitLastTask();

        $res = $this->algoliaHelper->getObjects(
            $this->indexPrefix .
            'default_products',
            [(string) self::SPECIAL_PRICE_TEST_PRODUCT_ID]
        );
        $algoliaProduct = reset($res['results']);

        if (!$algoliaProduct || !array_key_exists('price', $algoliaProduct)) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions.');
        }

        $this->assertEquals(32, $algoliaProduct['price']['USD']['default']);
        $this->assertEquals('', $algoliaProduct['price']['USD']['special_from_date']);
        $this->assertEquals('', $algoliaProduct['price']['USD']['special_to_date']);

        $specialPrice = 29;
        $fromDatetime = new \DateTime();
        $toDatetime = new \DateTime();
        $priceFrom = $fromDatetime->modify('-2 day')->format('Y-m-d H:i:s');
        $priceTo = $toDatetime->modify('+2 day')->format('Y-m-d H:i:s');

        $product = $this->objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->load(self::SPECIAL_PRICE_TEST_PRODUCT_ID);

        $product->setCustomAttributes([
            'special_price' => $specialPrice,
            'special_from_date' => date($priceFrom),
            'special_to_date' => date($priceTo),
        ]);
        $product->save();

        $this->productsIndexer->execute([self::SPECIAL_PRICE_TEST_PRODUCT_ID]);
        $this->algoliaHelper->waitLastTask();

        $res = $this->algoliaHelper->getObjects(
            $this->indexPrefix .
            'default_products',
            [(string) self::SPECIAL_PRICE_TEST_PRODUCT_ID]
        );
        $algoliaProduct = reset($res['results']);

        $this->assertEquals($specialPrice, $algoliaProduct['price']['USD']['default']);
        $this->assertEquals("$32.00", $algoliaProduct['price']['USD']['default_original_formated']);
    }

    private function updateStockItem($sku, $isInStock)
    {
        $stockItem = $this->stockRegistry->getStockItemBySku($sku);
        $stockItem->setIsInStock($isInStock);
        $this->stockRegistry->updateStockItemBySku($sku, $stockItem);
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

    protected function tearDown(): void
    {
        /** @var Product $product */
        $product = $this->objectManager->create(\Magento\Catalog\Model\Product::class);
        $product->load(self::SPECIAL_PRICE_TEST_PRODUCT_ID);

        $product->setCustomAttributes([
            'special_price' => null,
            'special_from_date' => null,
            'special_to_date' => null,
        ]);
        $product->getResource()->saveAttribute($product, 'special_price');
        $product->save();

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, true);

        parent::tearDown();
    }
}
