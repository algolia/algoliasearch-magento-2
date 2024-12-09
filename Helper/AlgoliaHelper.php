<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Search\ListIndicesResponse;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Magento\Framework\App\Helper\AbstractHelper;
use Exception;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;

class AlgoliaHelper extends AbstractHelper
{
    /**
     * @var string Case-sensitive object ID key
     */
    public const ALGOLIA_API_OBJECT_ID = 'objectID';

    /**
     * @var string
     */
    public const ALGOLIA_API_INDEX_NAME = 'indexName';

    /**
     * @var string
     */
    public const ALGOLIA_API_TASK_ID = 'taskID';

    /**
     * @var int
     */
    public const ALGOLIA_DEFAULT_SCOPE = 0;

    public function __construct(
        Context $context,
        protected AlgoliaConnector $algoliaConnector
    ){
        parent::__construct($context);
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->_getRequest();
    }

    /**
     * Ensure AlgoliaConnector targets the application configured on a particular store
     *
     * @param int|null $storeId
     * @return void
     */
    protected function handleStoreContext(int|null $storeId): void
    {
        if (!is_null($storeId)) {
            $this->algoliaConnector->setStoreId($storeId);
        }
    }

    /**
     * Restore AlgoliaConnector default Scope
     *
     * @return void
     */
    protected function restoreDefaultScope(): void
    {
        $this->algoliaConnector->setStoreId(self::ALGOLIA_DEFAULT_SCOPE);
    }

    /**
     * @param int|null $storeId
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient(int $storeId = null): SearchClient
    {
        $this->handleStoreContext($storeId);
        $client = $this->algoliaConnector->getClient();
        $this->restoreDefaultScope();
        return $client;
    }

    /**
     * @param int $storeId
     * @return void
     */
//    public function setStoreId(int $storeId): void
//    {
//        $this->algoliaConnector->setStoreId($storeId);
//    }

    /**
     * @param int|null $storeId
     *
     * @return ListIndicesResponse|array<string,mixed>
     * @throws AlgoliaException
     */
    public function listIndexes(int $storeId = null)
    {
        $this->handleStoreContext($storeId);
        $indexes = $this->algoliaConnector->listIndexes();
        $this->restoreDefaultScope();
        return $indexes;
    }

    /**
     * @param string $indexName
     * @param string $q
     * @param array $params
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function query(string $indexName, string $q, array $params, int $storeId = null): array
    {
        $this->handleStoreContext($storeId);
        $result = $this->algoliaConnector->query($indexName, $q, $params);
        $this->restoreDefaultScope();
        return $result;
    }

    /**
     * @param string $indexName
     * @param array $objectIds
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getObjects(string $indexName, array $objectIds, int $storeId = null): array
    {
        $this->handleStoreContext($storeId);
        $result = $this->algoliaConnector->getObjects($indexName, $objectIds);
        $this->restoreDefaultScope();
        return $result;
    }

    /**
     * @param $indexName
     * @param $settings
     * @param bool $forwardToReplicas
     * @param bool $mergeSettings
     * @param string $mergeSettingsFrom
     * @param int|null $storeId
     * @throws AlgoliaException
     */
    public function setSettings(
        $indexName,
        $settings,
        bool $forwardToReplicas = false,
        bool $mergeSettings = false,
        string $mergeSettingsFrom = '',
        int $storeId = null
    ) {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->setSettings(
            $indexName,
            $settings,
            $forwardToReplicas,
            $mergeSettings,
            $mergeSettingsFrom
        );
        $this->restoreDefaultScope();
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function deleteIndex(string $indexName, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->deleteIndex($indexName);
        $this->restoreDefaultScope();
    }

    /**
     * @param array $ids
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects(array $ids, string $indexName, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->deleteObjects($ids, $indexName);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function moveIndex(string $fromIndexName, string $toIndexName, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->moveIndex($fromIndexName, $toIndexName);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $key
     * @param array $params
     * @param int|null $storeId
     * @return string
     * @throws AlgoliaException
     */
    public function generateSearchSecuredApiKey(string $key, array $params = [], int $storeId = null): string
    {
        $this->handleStoreContext($storeId);
        $apiKey = $this->algoliaConnector->generateSearchSecuredApiKey($key, $params);
        $this->restoreDefaultScope();
        return $apiKey;
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getSettings(string $indexName, int $storeId = null): array
    {
        $this->handleStoreContext($storeId);
        $settings = $this->algoliaConnector->getSettings($indexName);
        $this->restoreDefaultScope();
        return $settings;
    }

    /**
     * Save objects to index (upserts records)
     * @param string $indexName
     * @param array $objects
     * @param bool $isPartialUpdate
     * @param int|null $storeId
     * @return void
     * @throws Exception
     */
    public function saveObjects(string $indexName, array $objects, bool $isPartialUpdate = false, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->saveObjects($indexName, $objects, $isPartialUpdate);
        $this->restoreDefaultScope();
    }

    /**
     * @param array<string, mixed> $rule
     * @param string $indexName
     * @param bool $forwardToReplicas
     * @return void
     * @throws AlgoliaException
     */
    public function saveRule(array $rule, string $indexName, bool $forwardToReplicas = false, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->saveRule($rule, $indexName, $forwardToReplicas);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $indexName
     * @param array $rules
     * @param bool $forwardToReplicas
     * @param null $storeId
     * @return void
     */
    public function saveRules(string $indexName, array $rules, bool $forwardToReplicas = false, $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->saveRules($indexName, $rules, $forwardToReplicas);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $indexName
     * @param string $objectID
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function deleteRule(
        string $indexName,
        string $objectID,
        bool $forwardToReplicas = false,
        int $storeId = null
    ) : void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->deleteRule($indexName, $objectID, $forwardToReplicas);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copySynonyms(string $fromIndexName, string $toIndexName, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->copySynonyms($fromIndexName, $toIndexName);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copyQueryRules(string $fromIndexName, string $toIndexName, int $storeId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->copyQueryRules($fromIndexName, $toIndexName);
        $this->restoreDefaultScope();
    }

    /**
     * @param string $indexName
     * @param array|null $searchRulesParams
     * @param int|null $storeId
     * @return array
     *
     * @throws AlgoliaException
     */
    public function searchRules(string $indexName, array$searchRulesParams = null, int $storeId = null)
    {
        $this->handleStoreContext($storeId);
        $rules = $this->algoliaConnector->searchRules($indexName, $searchRulesParams);
        $this->restoreDefaultScope();
        return $rules;
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
     * @param int|null $storeId
     * @param string|null $lastUsedIndexName
     * @param int|null $lastTaskId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function waitLastTask(int $storeId = null, string $lastUsedIndexName = null, int $lastTaskId = null): void
    {
        $this->handleStoreContext($storeId);
        $this->algoliaConnector->waitLastTask($lastUsedIndexName, $lastTaskId);
        $this->restoreDefaultScope();
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
     * @param int|null $storeId
     * @return int
     */
    public function getLastTaskId(int $storeId = null): int
    {
        $this->handleStoreContext($storeId);
        $lastTaskId = $this->algoliaConnector->getLastTaskId();
        $this->restoreDefaultScope();
        return $lastTaskId;
    }
}
