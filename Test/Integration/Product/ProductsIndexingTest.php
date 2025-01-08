<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\IndexOptions;
use Magento\Catalog\Model\Product;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Indexer\IndexerRegistry;

/**
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class ProductsIndexingTest extends ProductsIndexingTestCase
{

    /*** @var IndexerRegistry */
    protected $indexerRegistry;

    protected $productPriceIndexer;

    protected $testProductId;

    const OUT_OF_STOCK_PRODUCT_SKU = '24-MB01';

    public function testOnlyOnStockProducts()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productIndexer, 'products', $this->assertValues->productsOnStockCount);
    }

    public function testIncludingOutOfStock()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 1);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productIndexer, 'products', $this->assertValues->productsOutOfStockCount);
    }

    public function testDefaultIndexableAttributes()
    {
        $empty = $this->getSerializer()->serialize([]);

        $this->setConfig(ConfigHelper::PRODUCT_ATTRIBUTES, $empty);
        $this->setConfig(ConfigHelper::FACETS, $empty);
        $this->setConfig(ConfigHelper::SORTING_INDICES, $empty);
        $this->setConfig(ConfigHelper::PRODUCT_CUSTOM_RANKING, $empty);

        $this->productIndexer->executeRow($this->getValidTestProduct());
        $this->algoliaHelper->waitLastTask();

        $indexOptions = new IndexOptions([
            IndexOptionsInterface::ENFORCED_INDEX_NAME => $this->indexPrefix . 'default_products',
        ]);

        $results = $this->algoliaHelper->getObjects($indexOptions, [$this->getValidTestProduct()]);
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
        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, true);

        parent::tearDown();
    }
}
