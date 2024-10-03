<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Product;

use Algolia\AlgoliaSearch\Model\Indexer\Product;
use Algolia\AlgoliaSearch\Test\Integration\MultiStoreTestCase;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
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


    public function setUp():void
    {
        parent::setUp();

        $this->productsIndexer = $this->objectManager->get(Product::class);
        $this->productRepository = $this->objectManager->get(ProductRepositoryInterface::class);
        $this->productCollectionFactory = $this->objectManager->get(CollectionFactory::class);


        $this->productsIndexer->executeFull();
        $this->algoliaHelper->waitLastTask();
    }
}
