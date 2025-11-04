<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing\Product;

use Algolia\AlgoliaSearch\Api\Product\ProductRecordFieldsInterface;
use Algolia\AlgoliaSearch\Console\Command\Indexer\IndexProductsCommand;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Indexer\Product as ProductIndexer;
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

    protected $testProductId;

    const OUT_OF_STOCK_PRODUCT_SKU = '24-MB01';

    public function testOnlyOnStockProducts()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productBatchQueueProcessor, 'products', $this->assertValues->productsOnStockCount);
    }

    public function testIncludingOutOfStock()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 1);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $this->processTest($this->productBatchQueueProcessor, 'products', $this->assertValues->productsOutOfStockCount);
    }

    public function testDefaultIndexableAttributes()
    {
        $empty = $this->getSerializer()->serialize([]);

        $this->setConfig(ConfigHelper::PRODUCT_ATTRIBUTES, $empty);
        $this->setConfig(ConfigHelper::FACETS, $empty);
        $this->setConfig(ConfigHelper::SORTING_INDICES, $empty);
        $this->setConfig(ConfigHelper::PRODUCT_CUSTOM_RANKING, $empty);

        $this->productBatchQueueProcessor->processBatch(1, [$this->getValidTestProduct()]);
        $this->algoliaConnector->waitLastTask();

        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($this->indexPrefix . 'default_products');
        $results = $this->algoliaConnector->getObjects($indexOptions, [$this->getValidTestProduct()]);
        $hit = reset($results['results']);

        $defaultAttributes = [
            'objectID',
            'name',
            'url',
            ProductRecordFieldsInterface::VISIBILITY_SEARCH,
            ProductRecordFieldsInterface::VISIBILITY_CATALOG,
            'categories',
            'categories_without_path',
            'thumbnail_url',
            'image_url',
            'in_stock',
            //'price', since version 3.17.0, the price attribute is not mandatory if it's not present in any attributes list
            'type_id',
            'algoliaLastUpdateAtCET',
            'categoryIds',
        ];

        if (!$hit) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions on.');
        }

        foreach ($defaultAttributes as $attribute) {
            $this->assertArrayHasKey($attribute, $hit, 'Products attribute "' . $attribute . '" should be indexed but it is not"');
            unset($hit[$attribute]);
        }

        $this->assertArrayNotHasKey('price', $hit, 'Record has a price attribute but it should not');

        $extraAttributes = implode(', ', array_keys($hit));
        $this->assertEmpty($hit, 'Extra products attributes (' . $extraAttributes . ') are indexed and should not be.');
    }

    public function testIndexingProductsCommand()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $indexProductsCmd = $this->objectManager->get(IndexProductsCommand::class);
        $this->processCommandTest($indexProductsCmd, 'products', $this->assertValues->productsOnStockCount);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/full_indexing/products 0
     */
    public function testDisabledOldIndexer()
    {
        $productsIndexer = $this->objectManager->create(ProductIndexer::class);
        $this->processOldIndexerTest($productsIndexer, 'products', 0);
    }

    /**
     * @magentoConfigFixture current_store algoliasearch_indexing_manager/full_indexing/products 1
     */
    public function testEnabledOldIndexer()
    {
        $this->setConfig(ConfigHelper::SHOW_OUT_OF_STOCK, 0);

        $this->updateStockItem(self::OUT_OF_STOCK_PRODUCT_SKU, false);

        $productsIndexer = $this->objectManager->create(ProductIndexer::class);
        $this->processOldIndexerTest($productsIndexer, 'products', $this->assertValues->productsOnStockCount);
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
