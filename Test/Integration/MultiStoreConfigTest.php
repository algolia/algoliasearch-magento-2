<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

/**
 * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
 */
class MultiStoreConfigTest extends MultiStoreTestCase
{
    public function testMultiStoreIndicesCreation()
    {
        $websites = $this->storeManager->getWebsites();
        $stores = $this->storeManager->getStores();

        // Check that stores and websites are properly created
        $this->assertEquals(count($websites), 2);
        $this->assertEquals(count($stores), 3);

        foreach ($stores as $store) {
            $this->setupStore($store);
        }

        $indicesCreatedByTest = 0;
        $indices = $this->algoliaHelper->listIndexes();

        foreach ($indices['items'] as $index) {
            $name = $index['name'];

            if (mb_strpos($name, $this->indexPrefix) === 0) {
                $indicesCreatedByTest++;
            }
        }

        // Check that the configuration created the appropriate number of indices (4 per store => 3*4=12)
        $this->assertEquals($indicesCreatedByTest, 12);
    }
}
