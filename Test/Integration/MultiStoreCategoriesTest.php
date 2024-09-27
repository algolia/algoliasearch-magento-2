<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\Indexer\Category;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 * @magentoDbIsolation disabled
 * @magentoAppIsolation enabled
 */
class MultiStoreCategoriesTest extends MultiStoreTestCase
{
    /** @var Category */
    protected $categoriesIndexer;

    public function setUp():void
    {
        parent::setUp();

        /** @var Category $categoriesIndexer */
        $this->categoriesIndexer = $this->getObjectManager()->create(Category::class);

        $this->categoriesIndexer->executeFull();
        $this->algoliaHelper->waitLastTask();
    }

    public function testMultiStoreCategoryIndices()
    {
        foreach ($this->storeManager->getStores() as $store) {
            $this->assertNbOfRecordsPerStore($store->getCode(), 'categories', $this->assertValues->expectedCategory);
        }
    }
}
