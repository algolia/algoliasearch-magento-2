<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManager;

abstract class MultiStoreTestCase extends IndexingTestCase
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

        foreach ($this->storeManager->getStores() as $store) {
            $this->setupStore($store);
        }
    }

    protected function assertNbOfRecordsPerStore(string $storeCode, string $entity, int $expectedNumber)
    {
        $resultsDefault = $this->algoliaHelper->query($this->indexPrefix .  $storeCode . '_' . $entity, '', []);

        $this->assertEquals($expectedNumber, $resultsDefault['results'][0]['nbHits']);
    }

    protected function setupStore(StoreInterface $store): void
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
