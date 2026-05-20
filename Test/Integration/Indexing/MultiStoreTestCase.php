<?php

namespace Algolia\AlgoliaSearch\Test\Integration\Indexing;

use Algolia\AlgoliaSearch\Api\Processor\BatchQueueProcessorInterface;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\Configuration\InstantSearchHelper;
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
        ?array $categoryIds = null
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
        ?int $storeId = null
    ): void
    {
        $indexOptions = $this->getIndexOptions($entity, $storeId);

        $searchQuery = $this->searchQueryFactory->create([
            'indexOptions' => $indexOptions,
            'query' => '',
            'params' => [],
        ]);
        $resultsDefault = $this->algoliaConnector->query($searchQuery);

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
            path: 'algoliasearch_credentials/credentials/application_id',
            value: $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_APPLICATION_ID_ALT') ?
                getenv('ALGOLIA_APPLICATION_ID_ALT') :
                getenv('ALGOLIA_APPLICATION_ID'),
            scopeCode: $store->getCode()
        );
        $this->setConfig(
            path: 'algoliasearch_credentials/credentials/search_only_api_key',
            value: $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_SEARCH_KEY_ALT') ?
                getenv('ALGOLIA_SEARCH_KEY_ALT') :
                getenv('ALGOLIA_SEARCH_KEY'),
            scopeCode: $store->getCode()
        );
        $this->setConfig(
            path: 'algoliasearch_credentials/credentials/api_key',
            value: $store->getCode() === 'fixture_second_store' && getenv('ALGOLIA_API_KEY_ALT') ?
                getenv('ALGOLIA_API_KEY_ALT') :
                getenv('ALGOLIA_API_KEY'),
            scopeCode: $store->getCode()
        );
        $this->setConfig(
            path: 'algoliasearch_credentials/credentials/index_prefix',
            value: $this->indexPrefix,
            scopeCode: $store->getCode()
        );

        if ($enableInstantSearch) {
            $this->setConfig(
                path: InstantSearchHelper::IS_ENABLED,
                value: 1,
                scopeCode: $store->getCode()
            );
        }

        $this->indicesConfigurator->saveConfigurationToAlgolia($store->getId());
    }

    /**
     * Fetch an entity from an index and check its base url
     *
     * @param string $entity
     * @param string $entityId
     * @param StoreInterface $store
     * @param string $baseUrl
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function validateEntityUrl(string $entity, string $entityId, StoreInterface $store, string $baseUrl): void
    {
        $hit = $this->getEntityHit($entity, $entityId, $store);
        $this->assertStringContainsString($baseUrl, $hit['url']);
    }

    /**
     * @param string $entityId
     * @param StoreInterface $store
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function validateModelUrl(string $entityId, StoreInterface $store): void
    {
        $hit = $this->getEntityHit('products', $entityId, $store);
        // When area is adminhtml, the url returned by the backend model starts with http://localhost/index.php/backend
        // So we need to check if "backend" is not part of the url to assert that the frontend url model is called
        $this->assertStringNotContainsString('backend', $hit['url']);
    }

    /**
     * @param string $entity
     * @param string $entityId
     * @param StoreInterface $store
     * @return array
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    protected function getEntityHit(string $entity, string $entityId, StoreInterface $store): array
    {
        $indexOptions = $this->getIndexOptions($entity, $store->getId());
        $results = $this->algoliaConnector->getObjects($indexOptions, [$entityId]);

        return reset($results['results']);
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

                if (mb_strpos((string) $name, $this->indexPrefix) === 0) {
                    try {
                        // Keep buildWithEnforcedIndex here since we get the index name from the API and Magento has nothing to do with it
                        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($name, $store->getId());
                        $this->algoliaConnector->deleteIndex($indexOptions);
                        $deletedStoreIndices++;
                    } catch (AlgoliaException) {
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
