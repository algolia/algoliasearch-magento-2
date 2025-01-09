<?php

namespace Algolia\AlgoliaSearch\Service;

use Algolia\AlgoliaSearch\Api\Data\IndexOptionsInterface;
use Algolia\AlgoliaSearch\Api\SearchClient;
use Algolia\AlgoliaSearch\Configuration\SearchConfig;
use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Exceptions\ExceededRetriesException;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Search\ListIndicesResponse;
use Algolia\AlgoliaSearch\Model\Search\SettingsResponse;
use Algolia\AlgoliaSearch\Support\AlgoliaAgent;
use Exception;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaConnector
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

    /** @var int This value should be configured based on system/full_page_cache/ttl
     *           (which is by default 86400) and/or the configuration block TTL
     */
    protected const ALGOLIA_API_SECURED_KEY_TIMEOUT_SECONDS = 60 * 60 * 24; // TODO: Implement as config

    /** @var SearchClient[] */
    protected array $clients = [];

    protected ?int $maxRecordSize = null;

    /** @var string[] */
    protected array $potentiallyLongAttributes = ['description', 'short_description', 'meta_description', 'content'];

    /** @var string[] */
    protected array $nonCastableAttributes = ['sku', 'name', 'description', 'query'];

    /** @var bool */
    protected bool $userAgentsAdded = false;

    protected static ?string $lastUsedIndexName;

    protected static ?string $lastTaskId;

    protected static ?array $lastTaskInfoByStore;

    public function __construct(
        protected ConfigHelper $config,
        protected ManagerInterface $messageManager,
        protected ConsoleOutput $consoleOutput,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected IndexNameFetcher $indexNameFetcher
    ) {
        // Merge non castable attributes set in config
        $this->nonCastableAttributes = array_merge(
            $this->nonCastableAttributes,
            $this->config->getNonCastableAttributes()
        );
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     */
    protected function createClient(int $storeId = self::ALGOLIA_DEFAULT_SCOPE): void
    {
        if (!$this->algoliaCredentialsManager->checkCredentials($storeId)) {
            throw new AlgoliaException('Client initialization could not be performed because Algolia credentials were not provided.');
        }

        $config = SearchConfig::create(
            $this->config->getApplicationID($storeId),
            $this->config->getAPIKey($storeId)
        );
        $config->setConnectTimeout($this->config->getConnectionTimeout($storeId));
        $config->setReadTimeout($this->config->getReadTimeout($storeId));
        $config->setWriteTimeout($this->config->getWriteTimeout($storeId));
        $this->clients[$storeId] = SearchClient::createWithConfig($config);
    }

    /**
     * @param int $storeId
     * @return void
     * @throws AlgoliaException
     */
    protected function addAlgoliaUserAgent(int $storeId = self::ALGOLIA_DEFAULT_SCOPE): void
    {
        $clientName = $this->getClient($storeId)->getClientConfig()?->getClientName();

        if ($clientName) {
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento2 integration', $this->config->getExtensionVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'PHP', phpversion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Magento', $this->config->getMagentoVersion());
            AlgoliaAgent::addAlgoliaAgent($clientName, 'Edition', $this->config->getMagentoEdition());

            $this->userAgentsAdded = true;
        }
    }

    /**
     * @param int|null $storeId
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient(?int $storeId = self::ALGOLIA_DEFAULT_SCOPE): SearchClient
    {
        if (is_null($storeId)) {
            $storeId = self::ALGOLIA_DEFAULT_SCOPE;
        }

        if (!isset($this->clients[$storeId])) {
            $this->createClient($storeId);
            if (!$this->userAgentsAdded) {
                $this->addAlgoliaUserAgent($storeId);
            }
        }

        return $this->clients[$storeId];
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
     * @return ListIndicesResponse|array<string,mixed>
     * @throws AlgoliaException
     */
    public function listIndexes(?int $storeId = null)
    {
        return $this->getClient($storeId)->listIndices();
    }

    /**
     * @param IndexOptionsInterface $indexOptions
     * @param string $q
     * @param array $params
     * @return array<string, mixed>
     * @throws AlgoliaException|NoSuchEntityException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    public function query(IndexOptionsInterface $indexOptions, string $q, array $params): array
    {
        // TODO: Revisit - not compatible with PHP v4
        // if (isset($params['disjunctiveFacets'])) {
        //    return $this->searchWithDisjunctiveFaceting($indexName, $q, $params);
        //}

        $indexName = $this->getIndexName($indexOptions);

        $params = array_merge(
            [
                self::ALGOLIA_API_INDEX_NAME => $indexName,
                'query' => $q
            ],
            $params
        );

        // TODO: Validate return value for integration tests
        return $this->getClient($indexOptions->getStoreId())->search([
            'requests' => [ $params ]
        ]);
    }

    /**
     * @param IndexOptionsInterface $indexOptions
     * @param array $objectIds
     * @return array<string, mixed>
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function getObjects(IndexOptionsInterface $indexOptions, array $objectIds): array
    {
        $indexName = $this->getIndexName($indexOptions);

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

        return $this->getClient($indexOptions->getStoreId())->getObjects([ 'requests' => $requests ]);
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
        if ($mergeSettings === true) {
            $settings = $this->mergeSettings($indexName, $settings, $mergeSettingsFrom, $storeId);
        }

        $res = $this->getClient($storeId)->setSettings($indexName, $settings, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res, $storeId);
    }

    /**
     * @param string $indexName
     * @param array $requests
     * @param int|null $storeId
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    protected function performBatchOperation(string $indexName, array $requests, ?int $storeId = null): array
    {
        $response = $this->getClient($storeId)->batch($indexName, [ 'requests' => $requests ] );

        self::setLastOperationInfo($indexName, $response, $storeId);

        return $response;
    }

    /**
     * @param IndexOptionsInterface $indexOptions
     * @return void
     * @throws AlgoliaException|NoSuchEntityException
     */
    public function deleteIndex(IndexOptionsInterface $indexOptions): void
    {
        $indexName = $this->getIndexName($indexOptions);

        $res = $this->getClient($indexOptions->getStoreId())->deleteIndex($indexName);

        self::setLastOperationInfo($indexName, $res, $indexOptions->getStoreId());
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

        $this->performBatchOperation($indexName, $requests, $storeId);
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
        $response = $this->getClient($storeId)->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'move',
                'destination' => $toIndexName
            ]
        );
        self::setLastOperationInfo($toIndexName, $response, $storeId);
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
        // This is to handle a difference between API client v1 and v2.
        if (! isset($params['tagFilters'])) {
            $params['tagFilters'] = '';
        }

        $params['validUntil'] = time() + self::ALGOLIA_API_SECURED_KEY_TIMEOUT_SECONDS;

        return $this->getClient($storeId)->generateSecuredApiKey($key, $params);
    }

    /**
     * @param string $indexName
     * @return array<string, mixed>
     * @throws AlgoliaException
     */
    public function getSettings(string $indexName, ?int $storeId = null): array
    {
        try {
            return $this->getClient($storeId)->getSettings($indexName);
        } catch (Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
            return [];
        }
    }

    /**
     * @param string $indexName
     * @param array $settings
     * @param string $mergeSettingsFrom
     * @param int|null $storeId
     * @return SettingsResponse|array
     */
    protected function mergeSettings(
        string $indexName,
        array $settings,
        string $mergeSettingsFrom = '',
        ?int $storeId = null
    ): SettingsResponse|array
    {
        $onlineSettings = [];

        try {
            $sourceIndex = $indexName;
            if ($mergeSettingsFrom !== '') {
                $sourceIndex = $mergeSettingsFrom;
            }

            $onlineSettings = $this->getClient($storeId)->getSettings($sourceIndex);
        } catch (Exception $e) {
        }

        if (isset($settings['attributesToIndex'])) {
            $settings['searchableAttributes'] = $settings['attributesToIndex'];
            unset($settings['attributesToIndex']);
        }

        if (isset($onlineSettings['attributesToIndex'])) {
            $onlineSettings['searchableAttributes'] = $onlineSettings['attributesToIndex'];
            unset($onlineSettings['attributesToIndex']);
        }

        foreach ($this->getSettingsToRemove($onlineSettings) as $remove) {
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
     * These settings are to be managed by other processes
     * @param string[] $onlineSettings
     * @return string[]
     */
    protected function getSettingsToRemove(array $onlineSettings): array
    {
        $removals = ['slaves', 'replicas', 'decompoundedAttributes'];

        if (isset($onlineSettings['mode']) && $onlineSettings['mode'] == 'neuralSearch') {
            $removals[] = 'mode';
        }

        return array_merge($removals, $this->getSynonymSettingNames());
    }

    /**
     * @return string[]
     */
    protected function getSynonymSettingNames(): array
    {
        return [
            'synonyms',
            'altCorrections',
            'placeholders'
        ];
    }

    /**
     * Save objects to index (upserts records)
     * @param string $indexName
     * @param array $objects
     * @param bool $isPartialUpdate
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function saveObjects(string $indexName, array $objects, bool $isPartialUpdate = false, ?int $storeId = null): void
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

        $this->performBatchOperation($indexName, $requests, $storeId);
    }

    /**
     * @param string $indexName
     * @param array $response
     * @param int|null $storeId
     * @return void
     */
    protected static function setLastOperationInfo(string $indexName, array $response, ?int $storeId = null): void
    {
        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $response[self::ALGOLIA_API_TASK_ID] ?? null;

        if (!is_null($storeId)) {
            self::$lastTaskInfoByStore[$storeId] = [
                'indexName' => $indexName,
                'taskId' => $response[self::ALGOLIA_API_TASK_ID] ?? null
            ];
        }
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
        $res = $this->getClient($storeId)->saveRule(
            $indexName,
            $rule[self::ALGOLIA_API_OBJECT_ID],
            $rule,
            $forwardToReplicas
        );

        self::setLastOperationInfo($indexName, $res, $storeId);
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
        $res = $this->getClient($storeId)->saveRules($indexName, $rules, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res, $storeId);
    }


    /**
     * @param string $indexName
     * @param string $objectID
     * @param bool $forwardToReplicas
     * @return void
     * @throws AlgoliaException
     */
    public function deleteRule(
        string $indexName,
        string $objectID,
        bool $forwardToReplicas = false,
        ?int $storeId = null
    ): void
    {
        $res = $this->getClient($storeId)->deleteRule($indexName, $objectID, $forwardToReplicas);

        self::setLastOperationInfo($indexName, $res, $storeId);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function copySynonyms(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $response = $this->getClient($storeId)->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'copy',
                'destination' => $toIndexName,
                'scope'       => ['synonyms']
            ]
        );
        self::setLastOperationInfo($fromIndexName, $response, $storeId);
    }

    /**
     * @param string $fromIndexName
     * @param string $toIndexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function copyQueryRules(string $fromIndexName, string $toIndexName, ?int $storeId = null): void
    {
        $response = $this->getClient($storeId)->operationIndex(
            $fromIndexName,
            [
                'operation'   => 'copy',
                'destination' => $toIndexName,
                'scope'       => ['rules']
            ]
        );
        self::setLastOperationInfo($fromIndexName, $response, $storeId);
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
        return $this->getClient($storeId)->searchRules($indexName, $searchRulesParams);
    }

    /**
     * @param string $indexName
     * @param int|null $storeId
     * @return void
     * @throws AlgoliaException
     */
    public function clearIndex(string $indexName, ?int $storeId = null): void
    {
        $res = $this->getClient($storeId)->clearObjects($indexName);

        self::setLastOperationInfo($indexName, $res, $storeId);
    }

    /**
     * @param string|null $lastUsedIndexName
     * @param int|null $lastTaskId
     * @return void
     * @throws ExceededRetriesException|AlgoliaException
     */
    public function waitLastTask(?int $storeId = null, ?string $lastUsedIndexName = null, ?int $lastTaskId = null): void
    {
        if (is_null($lastUsedIndexName)) {
            if (!is_null($storeId) && isset(self::$lastTaskInfoByStore[$storeId])) {
                $lastUsedIndexName = self::$lastTaskInfoByStore[$storeId]['indexName'];
            } elseif (isset(self::$lastUsedIndexName)){
                $lastUsedIndexName = self::$lastUsedIndexName;
            }
        }

        if (is_null($lastTaskId)) {
            if (!is_null($storeId) && isset(self::$lastTaskInfoByStore[$storeId])) {
                $lastTaskId = self::$lastTaskInfoByStore[$storeId]['taskId'];
            } elseif (isset(self::$lastTaskId)){
                $lastTaskId = self::$lastTaskId;
            }
        }

        if (!$lastUsedIndexName || !$lastTaskId) {
            return;
        }

        if (is_null($storeId)) {
            $storeId = self::ALGOLIA_DEFAULT_SCOPE;
        }

        $this->getClient($storeId)->waitForTask($lastUsedIndexName, $lastTaskId);
    }

    /**
     * @param array $objects
     * @param string $indexName
     * @return void
     * @throws Exception
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
    protected function handleTooBigRecord($object): mixed
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
    protected function getLongestAttribute($object): int|string
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
    public function castProductObject(&$productData): void
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
    protected function castRecord($object): mixed
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
     * @return mixed
     */
    protected function castAttribute($value): mixed
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
     * @param int|null $storeId
     * @return int|null
     */
    public function getLastTaskId(?int $storeId = null): int|null
    {
        $lastTaskId = null;

        if (!is_null($storeId) && isset(self::$lastTaskInfoByStore[$storeId])) {
            $lastTaskId = self::$lastTaskInfoByStore[$storeId]['taskId'];
        } elseif (isset(self::$lastTaskId)){
            $lastTaskId = self::$lastTaskId;
        }

        return $lastTaskId;
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
     * @param int|null $storeId
     * @return mixed|null
     * @throws AlgoliaException
     * @internal This method is currently unstable and should not be used. It may be revisited ar fixed in a future version.
     */
    protected function searchWithDisjunctiveFaceting($indexName, $q, $params, ?int $storeId = null): mixed
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
        $results = $this->getClient($storeId)->multipleQueries(array_values($disjunctiveQueries));
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
    protected function getDisjunctiveQueries($queryParams): array
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
     * @return array
     */
    protected function getAlgoliaFiltersArrayWithoutCurrentRefinement($filters, $needle): array
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

    /**
     * @param IndexOptionsInterface $indexOptions
     * @return string|null
     * @throws NoSuchEntityException
     */
    protected function getIndexName(IndexOptionsInterface $indexOptions): ?string
    {
        return !is_null($indexOptions->getEnforcedIndexName()) ?
            $indexOptions->getEnforcedIndexName():
            $this->indexNameFetcher->getIndexName($indexOptions->getIndexSuffix(), $indexOptions->getStoreId());
    }
}
