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

/**
 * @deprecated (will be removed in v3.16.0)
 */
class AlgoliaHelper extends AbstractHelper
{
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
     * @param int|null $storeId
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = null): SearchClient
    {
        return $this->algoliaConnector->getClient($storeId);
    }

    /**
     * @param int|null $storeId
     *
     * @return ListIndicesResponse|array<string,mixed>
     * @throws AlgoliaException
     */
    public function listIndexes(?int $storeId = null)
    {
        return $this->algoliaConnector->listIndexes($storeId);
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
    public function query(string $indexName, string $q, array $params, ?int $storeId = null): array
    {
        return $this->algoliaConnector->query($indexName, $q, $params, $storeId);
    }

    /**
     * @param string $indexName
     * @param array $objectIds
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getObjects(string $indexName, array $objectIds, ?int $storeId = null): array
    {
        return $this->algoliaConnector->getObjects($indexName, $objectIds, $storeId);
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
        ?int $storeId = null
    ) {
        $this->algoliaConnector->setSettings(
            $indexName,
            $settings,
            $forwardToReplicas,
            $mergeSettings,
            $mergeSettingsFrom,
            $storeId
        );
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function deleteIndex(string $indexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->deleteIndex($indexName, $storeId);
    }

    /**
     * @param array $ids
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects(array $ids, string $indexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->deleteObjects($ids, $indexName, $storeId);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function moveIndex(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->moveIndex($fromIndexName, $toIndexName, $storeId);
    }

    /**
     * @param string $key
     * @param array $params
     * @param int|null $storeId
     * @return string
     * @throws AlgoliaException
     */
    public function generateSearchSecuredApiKey(string $key, array $params = [], ?int $storeId = null): string
    {
        return $this->algoliaConnector->generateSearchSecuredApiKey($key, $params, $storeId);
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getSettings(string $indexName, ?int $storeId = null): array
    {
        return $this->algoliaConnector->getSettings($indexName, $storeId);
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
    public function saveObjects(string $indexName, array $objects, bool $isPartialUpdate = false, ?int $storeId = null): void
    {
        $this->algoliaConnector->saveObjects($indexName, $objects, $isPartialUpdate, $storeId);
    }

    /**
     * @param array<string, mixed> $rule
     * @param string $indexName
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function saveRule(array $rule, string $indexName, bool $forwardToReplicas = false, ?int $storeId = null): void
    {
        $this->algoliaConnector->saveRule($rule, $indexName, $forwardToReplicas, $storeId);
    }

    /**
     * @param string $indexName
     * @param array $rules
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     */
    public function saveRules(string $indexName, array $rules, bool $forwardToReplicas = false, ?int $storeId = null): void
    {
        $this->algoliaConnector->saveRules($indexName, $rules, $forwardToReplicas, $storeId);
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
        ?int $storeId = null
    ) : void
    {
        $this->algoliaConnector->deleteRule($indexName, $objectID, $forwardToReplicas, $storeId);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copySynonyms(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->copySynonyms($fromIndexName, $toIndexName, $storeId);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function copyQueryRules(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->copyQueryRules($fromIndexName, $toIndexName, $storeId);
    }

    /**
     * @param string $indexName
     * @param array|null $searchRulesParams
     * @param int|null $storeId
     * @return array
     *
     * @throws AlgoliaException
     */
    public function searchRules(string $indexName, array$searchRulesParams = null, ?int $storeId = null)
    {
        return $this->algoliaConnector->searchRules($indexName, $searchRulesParams, $storeId);
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function clearIndex(string $indexName, ?int $storeId = null): void
    {
        $this->algoliaConnector->clearIndex($indexName, $storeId);
    }

    /**
     * @param int|null $storeId
     * @param string|null $lastUsedIndexName
     * @param int|null $lastTaskId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException
     */
    public function waitLastTask(?int $storeId = null, ?string $lastUsedIndexName = null, ?int $lastTaskId = null): void
    {
        $this->algoliaConnector->waitLastTask($storeId, $lastUsedIndexName, $lastTaskId);
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
