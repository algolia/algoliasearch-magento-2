<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Search\ListIndicesResponse;
use Algolia\AlgoliaSearch\Service\AlgoliaConnector;
use Algolia\AlgoliaSearch\Service\IndexOptionsBuilder;
use Magento\Framework\App\Helper\AbstractHelper;
use Exception;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * @deprecated (will be removed in v3.16.0)
 */
class AlgoliaHelper extends AbstractHelper
{
    public function __construct(
        Context $context,
        protected AlgoliaConnector $algoliaConnector,
        protected IndexOptionsBuilder $indexOptionsBuilder
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
     * @throws AlgoliaException|NoSuchEntityException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function query(string $indexName, string $q, array $params, ?int $storeId = null): array
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        return $this->algoliaConnector->query($indexOptions, $q, $params);
    }

    /**
     * @param string $indexName
     * @param array $objectIds
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function getObjects(string $indexName, array $objectIds, ?int $storeId = null): array
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        return $this->algoliaConnector->getObjects($indexOptions, $objectIds);
    }

    /**
     * @param $indexName
     * @param $settings
     * @param bool $forwardToReplicas
     * @param bool $mergeSettings
     * @param string $mergeSettingsFrom
     * @param int|null $storeId
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function setSettings(
        $indexName,
        $settings,
        bool $forwardToReplicas = false,
        bool $mergeSettings = false,
        string $mergeSettingsFrom = '',
        ?int $storeId = null
    ) {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->setSettings(
            $indexOptions,
            $settings,
            $forwardToReplicas,
            $mergeSettings,
            $mergeSettingsFrom
        );
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function deleteIndex(string $indexName, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->deleteIndex($indexOptions);
    }

    /**
     * @param array $ids
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function deleteObjects(array $ids, string $indexName, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->deleteObjects($ids, $indexOptions);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function moveIndex(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $fromIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($fromIndexName, $storeId);
        $toIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($toIndexName, $storeId);

        $this->algoliaConnector->moveIndex($fromIndexOptions, $toIndexOptions);
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
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function getSettings(string $indexName, ?int $storeId = null): array
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        return $this->algoliaConnector->getSettings($indexOptions);
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
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->saveObjects($indexOptions, $objects, $isPartialUpdate);
    }

    /**
     * @param array<string, mixed> $rule
     * @param string $indexName
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function saveRule(array $rule, string $indexName, bool $forwardToReplicas = false, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->saveRule($rule, $indexOptions, $forwardToReplicas);
    }

    /**
     * @param string $indexName
     * @param array $rules
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws NoSuchEntityException
     */
    public function saveRules(string $indexName, array $rules, bool $forwardToReplicas = false, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->saveRules($indexOptions, $rules, $forwardToReplicas);
    }

    /**
     * @param string $indexName
     * @param string $objectID
     * @param bool $forwardToReplicas
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function deleteRule(
        string $indexName,
        string $objectID,
        bool $forwardToReplicas = false,
        ?int $storeId = null
    ) : void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->deleteRule($indexOptions, $objectID, $forwardToReplicas);
    }

    /**
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function clearSynonyms(string $indexName, bool $forwardToReplicas = false, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);
        $this->algoliaConnector->clearSynonyms($indexOptions, $forwardToReplicas);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException|NoSuchEntityException
     */
    public function copySynonyms(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $fromIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($fromIndexName, $storeId);
        $toIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($toIndexName, $storeId);

        $this->algoliaConnector->copySynonyms($fromIndexOptions, $toIndexOptions);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     * @throws ExceededRetriesException|NoSuchEntityException
     */
    public function copyQueryRules(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $fromIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($fromIndexName, $storeId);
        $toIndexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($toIndexName, $storeId);

        $this->algoliaConnector->copyQueryRules($fromIndexOptions, $toIndexOptions);
    }

    /**
     * @param string $indexName
     * @param array|null $searchRulesParams
     * @param int|null $storeId
     * @return array
     *
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function searchRules(string $indexName, array $searchRulesParams = null, ?int $storeId = null)
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        return $this->algoliaConnector->searchRules($indexOptions, $searchRulesParams);
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function clearIndex(string $indexName, ?int $storeId = null): void
    {
        $indexOptions = $this->indexOptionsBuilder->buildWithEnforcedIndex($indexName, $storeId);

        $this->algoliaConnector->clearIndex($indexOptions);
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
     * @param int|null $storeId
     * @return int
     */
    public function getLastTaskId(?int $storeId = null): int
    {
        return $this->algoliaConnector->getLastTaskId($storeId);
    }
}
