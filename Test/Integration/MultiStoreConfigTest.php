<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;

class MultiStoreConfigTest extends TestCase
{
    /** @var StoreManager */
    protected $storeManager;

    /** @var IndicesConfigurator */
    protected $indicesConfigurator;

    public function setUp():void
    {
        /** @var StoreManager $storeManager */
        $this->storeManager = $this->getObjectManager()->create(StoreManager::class);

        /** @var IndicesConfigurator $indicesConfigurator */
        $this->indicesConfigurator = $this->getObjectManager()->create(IndicesConfigurator::class);

        parent::setUp();
    }

    /**
     * @magentoDataFixture Magento/Store/_files/second_website_with_two_stores.php
     */
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

    private function setupStore(StoreInterface $store): void
    {
        $this->setConfig(
            'algoliasearch_credentials/credentials/application_id',
            getenv('ALGOLIA_APPLICATION_ID'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/search_only_api_key',
            getenv('ALGOLIA_SEARCH_KEY_1') ?: getenv('ALGOLIA_SEARCH_API_KEY'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/api_key',
            getenv('ALGOLIA_API_KEY'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/index_prefix',
            $this->indexPrefix,
            $store->getCode()
        );

        $this->indicesConfigurator->saveConfigurationToAlgolia($store->getId());
    }
}
