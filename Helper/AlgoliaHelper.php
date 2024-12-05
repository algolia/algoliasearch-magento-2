<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Search\ListIndicesResponse;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Exception;

class AlgoliaHelper
{
    /**
     * @var string Case-sensitive object ID key
     */
    public const string ALGOLIA_API_OBJECT_ID = 'objectID';
    /**
     * @var string
     */
    public const string ALGOLIA_API_INDEX_NAME = 'indexName';
    /**
     * @var string
     */
    public const string ALGOLIA_API_TASK_ID = 'taskID';

    /**
     * @var int
     */
    public const int ALGOLIA_DEFAULT_SCOPE = 0;

    public function __construct(
        protected AlgoliaConnector $algoliaConnector
    ){}

    /**
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient(): SearchClient
    {
        return $this->algoliaConnector->getClient();
    }

    /**
     * @param int $storeId
     * @return void
     */
    public function setStoreId(int $storeId): void
    {
        $this->algoliaConnector->setStoreId($storeId);
    }

    /**
     * @return ListIndicesResponse|array<string,mixed>
     * @throws AlgoliaException
     */
    public function listIndexes()
    {
        return $this->algoliaConnector->listIndexes();
    }

    /**
     * @param string $indexName
     * @param string $q
     * @param array $params
     * @return array<string, mixed>
     * @throws AlgoliaException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function query(string $indexName, string $q, array $params): array
    {
        return $this->algoliaConnector->query($indexName, $q, $params);
    }

    /**
     * @param string $indexName
     * @param array $objectIds
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getObjects(string $indexName, array $objectIds): array
    {
        return $this->algoliaConnector->getObjects($indexName, $objectIds);
    }

    /**
     * @param $indexName
     * @param $settings
     * @param bool $forwardToReplicas
     * @param bool $mergeSettings
     * @param string $mergeSettingsFrom
     *
     * @throws AlgoliaException
     */
    public function setSettings(
        $indexName,
        $settings,
        bool $forwardToReplicas = false,
        bool $mergeSettings = false,
        string $mergeSettingsFrom = ''
    ) {
        $this->algoliaConnector->setSettings(
            $indexName,
            $settings,
            $forwardToReplicas,
            $mergeSettings,
            $mergeSettingsFrom
        );
    }

    /**
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteIndex(string $indexName): void
    {
        $this->algoliaConnector->deleteIndex($indexName);
    }

    /**
     * @param array $ids
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects(array $ids, string $indexName): void
    {
        $this->algoliaConnector->deleteObjects($ids, $indexName);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @return void
     * @throws AlgoliaException
     */
    public function moveIndex(string $fromIndexName, string $toIndexName): void
    {
        $this->algoliaConnector->moveIndex($fromIndexName, $toIndexName);
    }

    /**
     * @param string $key
     * @param array $params
     * @return string
     * @throws AlgoliaException
     */
    public function generateSearchSecuredApiKey(string $key, array $params = []): string
    {
        return $this->algoliaConnector->generateSearchSecuredApiKey($key, $params);
    }

    /**
     * @param string $indexName
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getSettings(string $indexName): array
    {
        return $this->algoliaConnector->getSettings($indexName);
    }

    /**
     * Save objects to index (upserts records)
     * @param string $indexName
     * @param array $objects
     * @param bool $isPartialUpdate
     * @return void
     * @throws Exception
     */
    public function saveObjects(string $indexName, array $objects, bool $isPartialUpdate = false): void
    {
        $this->algoliaConnector->saveObjects($indexName, $objects, $isPartialUpdate);
    }

    /**
     * @param array<string, mixed> $rule
     * @param string $indexName
     * @param bool $forwardToReplicas
     * @return void
     * @throws AlgoliaException
     */
    public function saveRule(array $rule, string $indexName, bool $forwardToReplicas = false): void
    {
        $this->algoliaConnector->saveRule($rule, $indexName, $forwardToReplicas);
    }

    /**
     * @param string $indexName
     * @param array $rules
     * @param bool $forwardToReplicas
     * @return void
     */
    public function saveRules(string $indexName, array $rules, bool $forwardToReplicas = false): void
    {
        $this->algoliaConnector->saveRules($indexName, $rules, $forwardToReplicas);
    }

    /**
     * @param string $indexName
     * @param string $objectID
     * @param bool $forwardToReplicas
     * @return void
     * @throws AlgoliaException
     */
    public function deleteRule(string $indexName, string $objectID, bool $forwardToReplicas = false): void
    {
        $this->algoliaConnector->deleteRule($indexName, $objectID, $forwardToReplicas);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copySynonyms(string $fromIndexName, string $toIndexName): void
    {
        $this->algoliaConnector->copySynonyms($fromIndexName, $toIndexName);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copyQueryRules(string $fromIndexName, string $toIndexName): void
    {
        $this->algoliaConnector->copyQueryRules($fromIndexName, $toIndexName);
    }

    /**
     * @param string $indexName
     * @param array|null $searchRulesParams
     *
     * @return array
     *
     * @throws AlgoliaException
     */
    public function searchRules(string $indexName, array$searchRulesParams = null)
    {
        return $this->algoliaConnector->searchRules($indexName, $searchRulesParams);
    }

    /**
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function clearIndex(string $indexName): void
    {
        $this->algoliaConnector->clearIndex($indexName);
    }

    /**
     * @param string|null $lastUsedIndexName
     * @param int|null $lastTaskId
     * @return void
     * @throws ExceededRetriesException|AlgoliaException
     */
    public function waitLastTask(string $lastUsedIndexName = null, int $lastTaskId = null): void
    {
        $this->algoliaConnector->waitLastTask($lastUsedIndexName, $lastTaskId);
    }

    /**
     * @param $productData
     * @return void
     */
    public function castProductObject(&$productData): void
    {
        $this->algoliaConnector->castProductObject($productData);
    }

    /**
     * @return int
     */
    public function getLastTaskId(): int
    {
        return $this->algoliaConnector->getLastTaskId();
    }
}
