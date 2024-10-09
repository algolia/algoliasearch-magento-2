<?php

namespace Algolia\AlgoliaSearch\Test\Integration;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Model\IndicesConfigurator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManager;

abstract class MultiStoreTestCase extends IndexingTestCase
{
    /** @var StoreManager */
    protected $storeManager;

    /** @var StoreRepositoryInterface */
    protected $storeRepository;

    /** @var IndicesConfigurator */
    protected $indicesConfigurator;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var StoreManager $storeManager */
        $this->storeManager = $this->objectManager->get(StoreManager::class);

        /** @var IndicesConfigurator $indicesConfigurator */
        $this->indicesConfigurator = $this->objectManager->get(IndicesConfigurator::class);

        /** @var StoreRepositoryInterface $storeRepository */
        $this->storeRepository = $this->objectManager->get(StoreRepositoryInterface::class);

        foreach ($this->storeManager->getStores() as $store) {
            $this->setupStore($store);
        }
    }

    /**
     * @param string $storeCode
     * @param string $entity
     * @param int $expectedNumber
     *
     * @return void
     * @throws AlgoliaException
     */
    protected function assertNbOfRecordsPerStore(string $storeCode, string $entity, int $expectedNumber): void
    {
        $resultsDefault = $this->algoliaHelper->query($this->indexPrefix .  $storeCode . '_' . $entity, '', []);

        $this->assertEquals($expectedNumber, $resultsDefault['results'][0]['nbHits']);
    }

    /**
     * @param string $indexName
     * @param string $recordId
     * @param array $expectedValues
     *
     * @return void
     * @throws AlgoliaException
     */
    public function assertAlgoliaRecordValues(
        string $indexName,
        string $recordId,
        array $expectedValues
    ) : void {
        $res = $this->algoliaHelper->getObjects($indexName, [$recordId]);
        $record = reset($res['results']);
        foreach ($expectedValues as $attribute => $expectedValue) {
            $this->assertEquals($expectedValue, $record[$attribute]);
        }
    }

    /**
     * @param StoreInterface $store
     *
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
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
