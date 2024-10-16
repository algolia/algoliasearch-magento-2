<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Model\Search\SearchRulesResponse;
use Algolia\AlgoliaSearch\Response\AbstractResponse;
use Algolia\AlgoliaSearch\Response\BatchIndexingResponse;
use Algolia\AlgoliaSearch\Response\MultiResponse;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

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

    /** @var int This value should be configured based on system/full_page_cache/ttl
     *           (which is by default 86400) and/or the configuration block TTL
     */
    protected const ALGOLIA_API_SECURED_KEY_TIMEOUT_SECONDS = 60 * 60 * 24; // TODO: Implement as config

    protected ?SearchClient $client = null;

    protected ConfigHelper $config;

    protected ManagerInterface $messageManager;

    protected ConsoleOutput $consoleOutput;

    protected ?int $maxRecordSize = null;

    /** @var string[] */
    protected array $potentiallyLongAttributes = ['description', 'short_description', 'meta_description', 'content'];

    /** @var string[] */
    protected array $nonCastableAttributes = ['sku', 'name', 'description', 'query'];

    protected static ?string $lastUsedIndexName;

    protected static ?int $lastTaskId;

    /**
     * @param Context $context
     * @param ConfigHelper $configHelper
     * @param ManagerInterface $messageManager
     * @param ConsoleOutput $consoleOutput
     */
    public function __construct(
        Context $context,
        ConfigHelper $configHelper,
        ManagerInterface $messageManager,
        ConsoleOutput $consoleOutput
    ) {
        parent::__construct($context);

        $this->config = $configHelper;
        $this->messageManager = $messageManager;
        $this->consoleOutput = $consoleOutput;

        $this->resetCredentialsFromConfig();

        // Merge non castable attributes set in config
        $this->nonCastableAttributes = array_merge(
            $this->nonCastableAttributes,
            $this->config->getNonCastableAttributes()
        );

        $clientName = $this->client?->getClientConfig()?->getClientName();

        if ($clientName) {
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento2 integration', $this->config->getExtensionVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'PHP', phpversion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento', $this->config->getMagentoVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Edition', $this->config->getMagentoEdition());
        }
    }

    /**
     * @return RequestInterface
     */
    public function getRequest(): RequestInterface
    {
        return $this->_getRequest();
    }

    /**
     * @return void
     */
    public function resetCredentialsFromConfig(): void
    {
        if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
            $config = SearchConfig::create($this->config->getApplicationID(), $this->config->getAPIKey());
            $config->setConnectTimeout($this->config->getConnectionTimeout());
            $config->setReadTimeout($this->config->getReadTimeout());
            $config->setWriteTimeout($this->config->getWriteTimeout());
            $this->client = SearchClient::createWithConfig($config);
        }
    }

    /**
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient(): SearchClient
    {
        $this->checkClient(__FUNCTION__);

        return $this->client;
    }

    /**
     * @param string $name
     * @throws AlgoliaException
     * @deprecated This method has been completely removed from the Algolia PHP connector version 4 and should not be used.
     */
    public function getIndex(string $name)
    {
        throw new AlgoliaException("This method is no longer supported for PHP client v4!");
    }

    /**
     * @return mixed
     * @throws AlgoliaException
     */
    public function listIndexes()
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->listIndices();
    }

    /**
     * @param $indexName
     * @param $q
     * @param $params
     * @return array<string, mixed>
     * @throws AlgoliaException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function query(string $indexName, string $q, array $params): array
    {
        $this->checkClient(__FUNCTION__);

        // TODO: Revisit - not compatible with PHP v4
        // if (isset($params['disjunctiveFacets'])) {
        //    return $this->searchWithDisjunctiveFaceting($indexName, $q, $params);
        //}

        $params = array_merge(
            [
                self::ALGOLIA_API_INDEX_NAME => $indexName,
                'query' => $q
            ],
            $params
        );

        // TODO: Validate return value for integration tests
        return $this->client->search([
            'requests' => [ $params ]
        ]);
    }

    /**
     * @param string $indexName
     * @param array $objectIds
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getObjects(string $indexName, array $objectIds): array
    {
        $this->checkClient(__FUNCTION__);

        $requests = array_values(
            array_map(
                function($id) use ($indexName) {
                    return [
                        self::ALGOLIA_API_INDEX_NAME => $indexName,
                        self::ALGOLIA_API_OBJECT_ID => $id
                    ];
                },
                $objectIds
            )
        );

        return $this->client->getObjects([ 'requests' => $requests ]);
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
        $forwardToReplicas = false,
        $mergeSettings = false,
        $mergeSettingsFrom = ''
    ) {
        $this->checkClient(__FUNCTION__);

        if ($mergeSettings === true) {
            $settings = $this->mergeSettings($indexName, $settings, $mergeSettingsFrom);
        }

        $res = $this->client->setSettings($indexName, $settings, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param string $indexName
     * @param array $requests
     * @return array<string, mixed>
     */
    protected function performBatchOperation(string $indexName, array $requests): array
    {
        $response = $this->client->batch($indexName, [ 'requests' => $requests ] );

        self::setLastOperationInfo($indexName, $response);

        return $response;
    }

    /**
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteIndex(string $indexName): void
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->deleteIndex($indexName);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param array $ids
     * @param string $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects(array $ids, string $indexName): void
    {
        $this->checkClient(__FUNCTION__);
        $requests = array_values(
            array_map(
                function ($id) {
                    return [
                        'action' => 'deleteObject',
                        'body'   => [
                            self::ALGOLIA_API_OBJECT_ID => $id
                        ]
                    ];
                },
                $ids
            )
        );

        $this->performBatchOperation($indexName, $requests);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @return void
     * @throws AlgoliaException
     */
    public function moveIndex(string $fromIndexName, string $toIndexName): void
    {
        $this->checkClient(__FUNCTION__);
        $response = $this->client->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'move',
                'destination' => $toIndexName
            ]
        );
        self::setLastOperationInfo($toIndexName, $response);
    }

    /**
     * @param string $key
     * @param array $params
     * @return string
     */
    public function generateSearchSecuredApiKey(string $key, array $params = []): string
    {
        // This is to handle a difference between API client v1 and v2.
        if (! isset($params['tagFilters'])) {
            $params['tagFilters'] = '';
        }

        $params['validUntil'] = time() + self::ALGOLIA_API_SECURED_KEY_TIMEOUT_SECONDS;

        return $this->client->generateSecuredApiKey($key, $params);
    }

    /**
     * @param $indexName
     * @return array<string, mixed>
     * @throws \Exception
     */
    public function getSettings(string $indexName): array
    {
        $this->checkClient(__FUNCTION__);

        try {
            return $this->client->getSettings($indexName);
        } catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
            return [];
        }
    }

    /**
     * @param $indexName
     * @param $settings
     * @param $mergeSettingsFrom
     * @return array|void
     */
    public function mergeSettings($indexName, $settings, $mergeSettingsFrom = '')
    {
        $onlineSettings = [];

        try {
            $sourceIndex = $indexName;
            if ($mergeSettingsFrom !== '') {
                $sourceIndex = $mergeSettingsFrom;
            }

            $onlineSettings = $this->client->getSettings($sourceIndex);
        } catch (\Exception $e) {
        }

        $removes = ['slaves', 'replicas', 'decompoundedAttributes'];

        if (isset($settings['attributesToIndex'])) {
            $settings['searchableAttributes'] = $settings['attributesToIndex'];
            unset($settings['attributesToIndex']);
        }

        if (isset($onlineSettings['attributesToIndex'])) {
            $onlineSettings['searchableAttributes'] = $onlineSettings['attributesToIndex'];
            unset($onlineSettings['attributesToIndex']);
        }

        foreach ($removes as $remove) {
            if (isset($onlineSettings[$remove])) {
                unset($onlineSettings[$remove]);
            }
        }

        foreach ($settings as $key => $value) {
            $onlineSettings[$key] = $value;
        }

        return $onlineSettings;
    }

    /**
     * Legacy function signature to add objects to Algolia
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws \Exception
     * @deprecated Do not use. This method has been replaced by saveObjects and may be removed in the future.
     */
    public function addObjects(array $objects, string $indexName): void {
        $this->saveObjects($indexName, $objects, $this->config->isPartialUpdateEnabled());
    }

    /**
     * Save objects to index (upserts records)
     * @param string $indexName
     * @param array $objects
     * @param bool $isPartialUpdate
     * @return void
     * @throws \Exception
     */
    public function saveObjects(string $indexName, array $objects, bool $isPartialUpdate = false): void
    {
        $this->prepareRecords($objects, $indexName);

        $action = $isPartialUpdate ? 'partialUpdateObject' : 'addObject';

        $requests = array_values(
            array_map(
                function ($object) use ($action) {
                    return [
                        'action' => $action,
                        'body'   => $object
                    ];
                },
                $objects
            )
        );

        $this->performBatchOperation($indexName, $requests);
    }

    /**
     * @param $indexName
     * @param $response
     * @return void
     */
    protected static function setLastOperationInfo(string $indexName, array $response): void
    {
        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $response[self::ALGOLIA_API_TASK_ID] ?? null;
    }

    /**
     * @param array<string, mixed> $rule
     * @param string $indexName
     * @param bool $forwardToReplicas
     * @return void
     */
    public function saveRule(array $rule, string $indexName, bool $forwardToReplicas = false): void
    {
        $res = $this->client->saveRule(
            $indexName,
            $rule[AlgoliaHelper::ALGOLIA_API_OBJECT_ID],
            $rule,
            $forwardToReplicas
        );

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param string $indexName
     * @param array $rules
     * @param bool $forwardToReplicas
     * @return void
     */
    public function saveRules(string $indexName, array $rules, bool $forwardToReplicas = false): void
    {
        $res = $this->client->saveRules($indexName, $rules, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res);
    }


    /**
     * @param string $indexName
     * @param string $objectID
     * @param bool $forwardToReplicas
     * @return void
     */
    public function deleteRule(string $indexName, string $objectID, bool $forwardToReplicas = false): void
    {
        $res = $this->client->deleteRule($indexName, $objectID, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $indexName
     * @param $synonyms
     * @return void
     * @throws AlgoliaException
     * @deprecated Managing synonyms from Magento is no longer supported. Use the Algolia dashboard instead.
     */
    public function setSynonyms($indexName, $synonyms): void
    {
        throw new AlgoliaException("This method is no longer supported for PHP client v4!");
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
        $this->checkClient(__FUNCTION__);
        $response = $this->client->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'copy',
                'destination' => $toIndexName,
                'scope'       => ['synonyms']
            ]
        );
        self::setLastOperationInfo($fromIndexName, $response);
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
        $this->checkClient(__FUNCTION__);
        $response = $this->client->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'copy',
                'destination' => $toIndexName,
                'scope'       => ['rules']
            ]
        );
        self::setLastOperationInfo($fromIndexName, $response);
    }

    /**
     * @param string $indexName
     * @param array|null $searchRulesParams
     *
     * @return SearchRulesResponse|mixed[]
     *
     * @throws AlgoliaException
     */
    public function searchRules(string $indexName, array$searchRulesParams = null)
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->searchRules($indexName, $searchRulesParams);
    }

    /**
     * @param $methodName
     * @return void
     * @throws AlgoliaException
     */
    protected function checkClient($methodName): void
    {
        if (isset($this->client)) {
            return;
        }

        $this->resetCredentialsFromConfig();

        if (!isset($this->client)) {
            $msg = 'Operation ' . $methodName . ' could not be performed because Algolia credentials were not provided.';

            throw new AlgoliaException($msg);
        }
    }

    /**
     * @param string $indexName
     * @return void
     */
    public function clearIndex(string $indexName): void
    {
        $this->checkClient(__FUNCTION__);

        $res = $this->client->clearObjects($indexName);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param string|null $lastUsedIndexName
     * @param int|null $lastTaskId
     * @return void
     * @throws ExceededRetriesException|AlgoliaException
     */
    public function waitLastTask(string $lastUsedIndexName = null, int $lastTaskId = null): void
    {
        $this->checkClient(__FUNCTION__);

        if ($lastUsedIndexName === null && isset(self::$lastUsedIndexName)) {
            $lastUsedIndexName = self::$lastUsedIndexName;
        }

        if ($lastTaskId === null && isset(self::$lastTaskId)) {
            $lastTaskId = self::$lastTaskId;
        }

        if (!$lastUsedIndexName || !$lastTaskId) {
            return;
        }

        $this->client->waitForTask($lastUsedIndexName, $lastTaskId);
    }

    /**
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws \Exception
     */
    protected function prepareRecords(array &$objects, string $indexName): void
    {
        $currentCET = strtotime('now');

        $modifiedIds = [];
        foreach ($objects as $key => &$object) {
            $object['algoliaLastUpdateAtCET'] = $currentCET;
            // Convert created_at to UTC timestamp
            if (isset($object['created_at'])) {
                $object['created_at'] = strtotime($object['created_at']);
            }

            $previousObject = $object;

            $object = $this->handleTooBigRecord($object);

            if ($object === false) {
                $longestAttribute = $this->getLongestAttribute($previousObject);
                $modifiedIds[] = $indexName . '
                    - ID ' . $previousObject[self::ALGOLIA_API_OBJECT_ID] . ' - skipped - longest attribute: ' . $longestAttribute;

                unset($objects[$key]);

                continue;
            } elseif ($previousObject !== $object) {
                $modifiedIds[] = $indexName . ' - ID ' . $previousObject[self::ALGOLIA_API_OBJECT_ID] . ' - truncated';
            }

            $object = $this->castRecord($object);
        }

        if ($modifiedIds && $modifiedIds !== []) {
            $separator = php_sapi_name() === 'cli' ? "\n" : '<br>';

            $errorMessage = 'Algolia reindexing:
                You have some records which are too big to be indexed in Algolia.
                They have either been truncated
                (removed attributes: ' . implode(', ', $this->potentiallyLongAttributes) . ')
                or skipped completely: ' . $separator . implode($separator, $modifiedIds);

            if (php_sapi_name() === 'cli') {
                $this->consoleOutput->writeln($errorMessage);

                return;
            }

            $this->messageManager->addErrorMessage($errorMessage);
        }
    }

    /**
     * @return int
     */
    protected function getMaxRecordSize(): int
    {
        if (!$this->maxRecordSize) {
            $this->maxRecordSize = $this->config->getMaxRecordSizeLimit();
        }

        return $this->maxRecordSize;
    }

    /**
     * @param $object
     * @return false|mixed
     */
    protected function handleTooBigRecord($object)
    {
        $size = $this->calculateObjectSize($object);

        if ($size > $this->getMaxRecordSize()) {
            foreach ($this->potentiallyLongAttributes as $attribute) {
                if (isset($object[$attribute])) {
                    unset($object[$attribute]);

                    // Recalculate size and check if it fits in Algolia index
                    $size = $this->calculateObjectSize($object);
                    if ($size < $this->getMaxRecordSize()) {
                        return $object;
                    }
                }
            }

            // If the SKU attribute is the longest, start popping off SKU's to make it fit
            // This has the downside that some products cannot be found on some of its childrens' SKU's
            // But at least the config product can be indexed
            // Always keep the original SKU though
            if ($this->getLongestAttribute($object) === 'sku' && is_array($object['sku'])) {
                foreach ($object['sku'] as $sku) {
                    if (count($object['sku']) === 1) {
                        break;
                    }

                    array_pop($object['sku']);

                    $size = $this->calculateObjectSize($object);
                    if ($size < $this->getMaxRecordSize()) {
                        return $object;
                    }
                }
            }

            // Recalculate size, if it still does not fit, let's skip it
            $size = $this->calculateObjectSize($object);
            if ($size > $this->getMaxRecordSize()) {
                $object = false;
            }
        }

        return $object;
    }

    /**
     * @param $object
     * @return int|string
     */
    protected function getLongestAttribute($object)
    {
        $maxLength = 0;
        $longestAttribute = '';

        foreach ($object as $attribute => $value) {
            $attributeLength = mb_strlen(json_encode($value));

            if ($attributeLength > $maxLength) {
                $longestAttribute = $attribute;

                $maxLength = $attributeLength;
            }
        }

        return $longestAttribute;
    }

    /**
     * @param $productData
     * @return void
     */
    public function castProductObject(&$productData)
    {
        foreach ($productData as $key => &$data) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }

            $data = $this->castAttribute($data);

            if (is_array($data) === false) {
                if ($data != null) {
                    $data = explode('|', $data);
                    if (count($data) === 1) {
                        $data = $data[0];
                        $data = $this->castAttribute($data);
                    } else {
                        foreach ($data as &$element) {
                            $element = $this->castAttribute($element);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param $object
     * @return mixed
     */
    protected function castRecord($object)
    {
        foreach ($object as $key => &$value) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }

            $value = $this->castAttribute($value);
        }

        return $object;
    }

    /**
     * This method serves to prevent parse of float values that exceed PHP_FLOAT_MAX as INF will break
     * JSON encoding.
     *
     * To further customize the handling of values that may be incorrectly interpreted as numeric by
     * PHP you can implement an "after" plugin on this method.
     *
     * @param $value - what PHP thinks is a floating point number
     * @return bool
     */
    public function isValidFloat(string $value) : bool {
        return floatval($value) !== INF;
    }

    /**
     * @param $value
     * @return float|int
     */
    protected function castAttribute($value)
    {
        if (is_numeric($value) && floatval($value) === floatval((int) $value)) {
            return (int) $value;
        }

        if (is_numeric($value) && $this->isValidFloat($value)) {
            return floatval($value);
        }

        return $value;
    }

    /**
     * @return string
     */
    public function getLastIndexName(): string
    {
        return self::$lastUsedIndexName;
    }

    /**
     * @return int
     */
    public function getLastTaskId(): int
    {
        return self::$lastTaskId;
    }

    /**
     * @param $object
     *
     * @return int
     */
    protected function calculateObjectSize($object): int
    {
        return mb_strlen(json_encode($object));
    }

    /**
     * @param $indexName
     * @param $q
     * @param $params
     * @return mixed|null
     * @throws AlgoliaException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    protected function searchWithDisjunctiveFaceting($indexName, $q, $params)
    {
        throw new AlgoliaException("This function is not currently supported on PHP connector v4");

        // TODO: Revisit this implementation for backend render
        if (! is_array($params['disjunctiveFacets']) || count($params['disjunctiveFacets']) <= 0) {
            throw new \InvalidArgumentException('disjunctiveFacets needs to be an non empty array');
        }

        if (isset($params['filters'])) {
            throw new \InvalidArgumentException('You can not use disjunctive faceting and the filters parameter');
        }

        /**
         * Prepare queries
         */
        // Get the list of disjunctive queries to do: 1 per disjunctive facet
        $disjunctiveQueries = $this->getDisjunctiveQueries($params);

        // Format disjunctive queries for multipleQueries call
        foreach ($disjunctiveQueries as &$disjunctiveQuery) {
            $disjunctiveQuery[self::ALGOLIA_API_INDEX_NAME] = $indexName;
            $disjunctiveQuery['query'] = $q;
            unset($disjunctiveQuery['disjunctiveFacets']);
        }

        // Merge facets and disjunctiveFacets for the hits query
        $facets = $params['facets'] ?? [];
        $facets = array_merge($facets, $params['disjunctiveFacets']);
        unset($params['disjunctiveFacets']);

        // format the hits query for multipleQueries call
        $params['query'] = $q;
        $params[self::ALGOLIA_API_INDEX_NAME] = $indexName;
        $params['facets'] = $facets;

        // Put the hit query first
        array_unshift($disjunctiveQueries, $params);

        /**
         * Do all queries in one call
         */
        $results = $this->client->multipleQueries(array_values($disjunctiveQueries));
        $results = $results['results'];

        /**
         * Merge facets from disjunctive queries with facets from the hits query
         */
        // The first query is the hits query that the one we'll return to the user
        $queryResults = array_shift($results);

        // To be able to add facets from disjunctive query we create 'facets' key in case we only have disjunctive facets
        if (false === isset($queryResults['facets'])) {
            $queryResults['facets'] =[];
        }

        foreach ($results as $disjunctiveResults) {
            if (isset($disjunctiveResults['facets'])) {
                foreach ($disjunctiveResults['facets'] as $facetName => $facetValues) {
                    $queryResults['facets'][$facetName] = $facetValues;
                }
            }
        }

        return $queryResults;
    }

    /**
     * @param $queryParams
     * @return array
     */
    protected function getDisjunctiveQueries($queryParams)
    {
        $queriesParams = [];

        foreach ($queryParams['disjunctiveFacets'] as $facetName) {
            $params = $queryParams;
            $params['facets'] = [$facetName];
            $facetFilters = isset($params['facetFilters']) ? $params['facetFilters'] : [];
            $numericFilters = isset($params['numericFilters']) ? $params['numericFilters'] : [];

            $additionalParams = [
                'hitsPerPage' => 1,
                'page' => 0,
                'attributesToRetrieve' => [],
                'attributesToHighlight' => [],
                'attributesToSnippet' => [],
                'analytics' => false,
            ];

            $additionalParams['facetFilters'] =
                $this->getAlgoliaFiltersArrayWithoutCurrentRefinement($facetFilters, $facetName . ':');
            $additionalParams['numericFilters'] =
                $this->getAlgoliaFiltersArrayWithoutCurrentRefinement($numericFilters, $facetName);

            $queriesParams[$facetName] = array_merge($params, $additionalParams);
        }

        return $queriesParams;
    }

    /**
     * @param $filters
     * @param $needle
     * @return mixed
     */
    protected function getAlgoliaFiltersArrayWithoutCurrentRefinement($filters, $needle)
    {
        // iterate on each filters which can be string or array and filter out every refinement matching the needle
        for ($i = 0; $i < count($filters); $i++) {
            if (is_array($filters[$i])) {
                foreach ($filters[$i] as $filter) {
                    if (mb_substr($filter, 0, mb_strlen($needle)) === $needle) {
                        unset($filters[$i]);
                        $filters = array_values($filters);
                        $i--;

                        break;
                    }
                }
            } else {
                if (mb_substr($filters[$i], 0, mb_strlen($needle)) === $needle) {
                    unset($filters[$i]);
                    $filters = array_values($filters);
                    $i--;
                }
            }
        }

        return $filters;
    }
}
