<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
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

    protected function reindexToAllStores(
        BatchQueueProcessorInterface $batchQueueProcessor,
        array $categoryIds = null
    ): void
    {
        foreach (array_keys($this->storeManager->getStores()) as $storeId) {
            $batchQueueProcessor->processBatch($storeId, $categoryIds);
            $this->algoliaConnector->waitLastTask($storeId);
        }
    }

    /**
     * @param string $storeCode
     * @param string $entity
     * @param int $expectedNumber
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    protected function assertNbOfRecordsPerStore(
        string $storeCode,
        string $entity,
        int $expectedNumber,
        int $storeId = null
    ): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex(
            $this->indexPrefix .  $storeCode . '_' . $entity,
            $storeId
        );

        $resultsDefault = $this->algoliaConnector->query($indexOptions, '', []);

        $this->assertEquals($expectedNumber, $resultsDefault['results'][0]['nbHits']);
    }

    /**
     * @param StoreInterface $store
     * @param bool $enableInstantSearch
     *
     * @return void
     * @throws AlgoliaException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     */
    protected function setupStore(StoreInterface $store, bool $enableInstantSearch = false): void
    {
        $this->setConfig(
            'algoliasearch_credentials/credentials/application_id',
            $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_APPLICATION_ID_ALT') ?
                getenv('ALGOLIA_APPLICATION_ID_ALT') :
                getenv('ALGOLIA_APPLICATION_ID'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/search_only_api_key',
            $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_SEARCH_KEY_ALT') ?
                getenv('ALGOLIA_SEARCH_KEY_ALT') :
                getenv('ALGOLIA_SEARCH_KEY'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/api_key',
            $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_API_KEY_ALT') ?
                getenv('ALGOLIA_API_KEY_ALT') :
                getenv('ALGOLIA_API_KEY'),
            $store->getCode()
        );
        $this->setConfig(
            'algoliasearch_credentials/credentials/index_prefix',
            $this->indexPrefix,
            $store->getCode()
        );

        if ($enableInstantSearch) {
            $this->setConfig(ConfigHelper::IS_INSTANT_ENABLED, 1, $store->getCode());
        }

        $this->indicesConfigurator->saveConfigurationToAlgolia($store->getId());
    }

    /**
     * @throws ExceededRetriesException
     * @throws AlgoliaException
     */
    protected function tearDown(): void
    {
        $this->clearStoresIndices(true);
        $this->clearStoresIndices(); // Remaining replicas
    }

    protected function clearStoresIndices($wait = false)
    {
        foreach ($this->storeManager->getStores() as $store) {
            $deletedStoreIndices = 0;

            $indices = $this->algoliaConnector->listIndexes($store->getId());

            foreach ($indices['items'] as $index) {
                $name = $index['name'];

                if (mb_strpos($name, $this->indexPrefix) === 0) {
                    try {
                        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($name, $store->getId());
                        $this->algoliaConnector->deleteIndex($indexOptions);
                        $deletedStoreIndices++;
                    } catch (AlgoliaException $e) {
                        // Might be a replica
                    }
                }
            }

            if ($deletedStoreIndices > 0 && $wait) {
                $this->algoliaConnector->waitLastTask($store->getId());
            }
        }
    }
}
