<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * @magentoDataFixture ../../../../app/code/Algolia/AlgoliaSearch/Test/Integration/_files/second_website_with_two_stores_and_products.php
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiStoreProductsTest extends MultiStoreTestCase
{
    /** @var Product */
    protected $productsIndexer;

    /** @var ProductRepositoryInterface */
    protected $productRepository;

    /**  @var CollectionFactory */
    private $productCollectionFactory;

    protected $skusToAssign = [
      '24-MB01',
      '24-MB04',
      '24-MB03',
      '24-MB05',
      '24-MB06'
    ];

    public function setUp():void
    {
        parent::setUp();

        $this->productsIndexer = $this->objectManager->get(Product::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productCollectionFactory = $this->objectManager->get(CollectionFactory::class);

        $this->productsIndexer->executeFull();
    }

    public function testMultiStoreProductIndices()
    {
        // Check that every store has the right number of products
        foreach ($this->storeManager->getStores() as $store) {
            $this->assertNbOfRecordsPerStore(
                $store->getCode(),
                'products',
                $store->getCode() === 'default' ?
                    $this->assertValues->productsCountWithoutGiffcards :
                    count($this->skusToAssign)
            );
        }
    }
}
