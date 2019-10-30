<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Magento\CatalogInventory\Model\StockRegistry;

class ProductsIndexingTest extends IndexingTestCase
{
    public function testOnlyOnStockProducts()
    {
        $this->setConfig('cataloginventory/options/show_out_of_stock', 0);

        $this->setOneProductOutOfStock();

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');

        $this->processTest($indexer, 'products', $this->assertValues->productsOnStockCount);
    }

    public function testIncludingOutOfStock()
    {
        $this->setConfig('cataloginventory/options/show_out_of_stock', 1);

        $this->setOneProductOutOfStock();

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');

        $this->processTest($indexer, 'products', $this->assertValues->productsOutOfStockCount);
    }

    public function testDefaultIndexableAttributes()
    {
        $empty = $this->getSerializer()->serialize([]);

        $this->setConfig('algoliasearch_products/products/product_additional_attributes', $empty);
        $this->setConfig('algoliasearch_instant/instant/facets', $empty);
        $this->setConfig('algoliasearch_instant/instant/sorts', $empty);
        $this->setConfig('algoliasearch_products/products/custom_ranking_product_attributes', $empty);

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->executeRow(994);

        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_products', ['994']);
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

    public function testNoProtocolImageUrls()
    {
        $additionAttributes = $this->configHelper->getProductAdditionalAttributes();
        $additionAttributes[] = [
            'attribute' => 'media_gallery',
            'searchable' => '0',
            'retrievable' => '1',
            'order' => 'unordered',
        ];

        $this->setConfig(
            'algoliasearch_products/products/product_additional_attributes',
            $this->getSerializer()->serialize($additionAttributes)
        );

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->executeRow(994);

        $this->algoliaHelper->waitLastTask();

        $results = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_products', ['994']);
        $hit = reset($results['results']);

        if (!$hit || !array_key_exists('image_url', $hit)) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions on.');
        }

        $this->assertStringStartsWith('//', $hit['image_url']);
        $this->assertStringStartsWith('//', $hit['thumbnail_url']);

        $this->assertArrayHasKey('media_gallery', $hit);

        foreach ($hit['media_gallery'] as $galleryImageUrl) {
            $this->assertStringStartsWith('//', $galleryImageUrl);
        }
    }

    public function testNoSpecialPrice()
    {
        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->execute([9]);

        $this->algoliaHelper->waitLastTask();

        $res = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_products', ['9']);
        $algoliaProduct = reset($res['results']);

        if (!$algoliaProduct || !array_key_exists('price', $algoliaProduct)) {
            $this->markTestIncomplete('Hit was not returned correctly from Algolia. No Hit to run assetions.');
        }

        $this->assertEquals(32, $algoliaProduct['price']['USD']['default']);
        $this->assertFalse($algoliaProduct['price']['USD']['special_from_date']);
        $this->assertFalse($algoliaProduct['price']['USD']['special_to_date']);
    }

    public function testSpecialPrice()
    {
        /** @var \Magento\Framework\App\ProductMetadataInterface $productMetadata */
        $productMetadata = $this->getObjectManager()->create('\Magento\Framework\App\ProductMetadataInterface');
        $version = $productMetadata->getVersion();
        if (version_compare($version, '2.1', '<') === true) {
            $this->markTestSkipped();
        }

        /** @var \Magento\Catalog\Model\Product $product */
        $product = $this->getObjectManager()->create('\Magento\Catalog\Model\Product');
        $product->load(9);

        $specialPrice = 29;
        $from = 1452556800;
        $to = 1573516800;
        $product->setCustomAttributes([
            'special_price' => $specialPrice,
            'special_from_date' => date('m/d/Y', $from),
            'special_to_date' => date('m/d/Y', $to),
        ]);

        $product->save();

        /** @var Product $indexer */
        $indexer = $this->getObjectManager()->create('\Algolia\AlgoliaSearch\Model\Indexer\Product');
        $indexer->execute([9]);

        $this->algoliaHelper->waitLastTask();

        $res = $this->algoliaHelper->getObjects($this->indexPrefix . 'default_products', ['9']);
        $algoliaProduct = reset($res['results']);

        $this->assertEquals($specialPrice, $algoliaProduct['price']['USD']['default']);
        $this->assertEquals($from, $algoliaProduct['price']['USD']['special_from_date']);
        $this->assertEquals($to, $algoliaProduct['price']['USD']['special_to_date']);
    }

    private function setOneProductOutOfStock()
    {
        /** @var StockRegistry $stockRegistry */
        $stockRegistry = $this->getObjectManager()->create('Magento\CatalogInventory\Model\StockRegistry');
        $stockItem = $stockRegistry->getStockItemBySku('24-MB01');
        $stockItem->setIsInStock(false);
        $stockRegistry->updateStockItemBySku('24-MB01', $stockItem);
    }
}
