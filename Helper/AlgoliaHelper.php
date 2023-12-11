<?php

namespace Algolia\AlgoliaSearch\Helper;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Algolia\AlgoliaSearch\Response\AbstractResponse;
use Algolia\AlgoliaSearch\Response\BatchIndexingResponse;
use Algolia\AlgoliaSearch\Response\MultiResponse;
use Algolia\AlgoliaSearch\SearchClient;
use Algolia\AlgoliaSearch\SearchIndex;
use Algolia\AlgoliaSearch\Support\UserAgent;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaHelper extends AbstractHelper
{
    /** @var SearchClient */
    protected $client;

    /** @var ConfigHelper */
    protected $config;

    /** @var ManagerInterface */
    protected $messageManager;

    /** @var ConsoleOutput */
    protected $consoleOutput;

    /** @var int */
    protected $maxRecordSize;

    /** @var array */
    protected $potentiallyLongAttributes = ['description', 'short_description', 'meta_description', 'content'];

    /** @var array */
    protected $nonCastableAttributes = ['sku', 'name', 'description', 'query'];

    /** @var string */
    protected static $lastUsedIndexName;

    /** @var string */
    protected static $lastTaskId;

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

        UserAgent::addCustomUserAgent('Magento2 integration', $this->config->getExtensionVersion());
        UserAgent::addCustomUserAgent('PHP', phpversion());
        UserAgent::addCustomUserAgent('Magento', $this->config->getMagentoVersion());
        UserAgent::addCustomUserAgent('Edition', $this->config->getMagentoEdition());
    }

    /**
     * @return \Magento\Framework\App\RequestInterface
     */
    public function getRequest()
    {
        return $this->_getRequest();
    }

    /**
     * @return void
     */
    public function resetCredentialsFromConfig()
    {
        if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
            $this->client = SearchClient::create(
                $this->config->getApplicationID(),
                $this->config->getAPIKey()
            );
        }
    }

    /**
     * @return SearchClient
     * @throws AlgoliaException
     */
    public function getClient()
    {
        $this->checkClient(__FUNCTION__);

        return $this->client;
    }

    /**
     * @param $name
     * @return SearchIndex
     * @throws AlgoliaException
     */
    public function getIndex($name)
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->initIndex($name);
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
     * @return mixed|null
     * @throws AlgoliaException
     */
    public function query($indexName, $q, $params)
    {
        $this->checkClient(__FUNCTION__);

        if (isset($params['disjunctiveFacets'])) {
            return $this->searchWithDisjunctiveFaceting($indexName, $q, $params);
        }

        return $this->client->initIndex($indexName)->search($q, $params);
    }

    /**
     * @param $indexName
     * @param $objectIds
     * @return mixed
     * @throws AlgoliaException
     */
    public function getObjects($indexName, $objectIds)
    {
        $this->checkClient(__FUNCTION__);

        return $this->getIndex($indexName)->getObjects($objectIds);
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

        $index = $this->getIndex($indexName);

        if ($mergeSettings === true) {
            $settings = $this->mergeSettings($indexName, $settings, $mergeSettingsFrom);
        }

        $res = $index->setSettings($settings, [
            'forwardToReplicas' => $forwardToReplicas,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteIndex($indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->initIndex($indexName)->delete()->wait();

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $ids
     * @param $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function deleteObjects($ids, $indexName)
    {
        $this->checkClient(__FUNCTION__);

        $index = $this->getIndex($indexName);

        $res = $index->deleteObjects($ids);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $tmpIndexName
     * @param $indexName
     * @return void
     * @throws AlgoliaException
     */
    public function moveIndex($tmpIndexName, $indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->moveIndex($tmpIndexName, $indexName);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $key
     * @param $params
     * @return string
     */
    public function generateSearchSecuredApiKey($key, $params = [])
    {
        // This is to handle a difference between API client v1 and v2.
        if (! isset($params['tagFilters'])) {
            $params['tagFilters'] = '';
        }

        return SearchClient::generateSecuredApiKey($key, $params);
    }

    /**
     * @param $indexName
     * @return void
     * @throws \Exception
     */
    public function getSettings($indexName)
    {
        try {
            return $this->getIndex($indexName)->getSettings();
        }catch (\Exception $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
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

            $onlineSettings = $this->getSettings($sourceIndex);
        } catch (\Exception $e) {
        }

        $removes = ['slaves', 'replicas'];

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
     * @param $objects
     * @param $indexName
     * @return void
     * @throws \Algolia\AlgoliaSearch\Exceptions\MissingObjectId
     */
    public function addObjects($objects, $indexName)
    {
        $this->prepareRecords($objects, $indexName);

        $index = $this->getIndex($indexName);

        if ($this->config->isPartialUpdateEnabled()) {
            $response = $index->partialUpdateObjects($objects, [
                'createIfNotExists' => true,
            ]);
        } else {
            $response = $index->saveObjects($objects, [
                'autoGenerateObjectIDIfNotExist' => true,
            ]);
        }

        self::setLastOperationInfo($indexName, $response);
    }

    /**
     * @param $indexName
     * @param $response
     * @return void
     */
    protected static function setLastOperationInfo($indexName, $response)
    {
        self::$lastUsedIndexName = $indexName;

        if ($response instanceof BatchIndexingResponse || $response instanceof MultiResponse) {
            foreach ($response->getBody() as $res) {
                $response = $res;
            }
        }

        if ($response instanceof AbstractResponse) {
            $response = $response->getBody();
        }

        self::$lastTaskId = isset($response['taskID']) ? $response['taskID'] : null;
    }

    /**
     * @param $rule
     * @param $indexName
     * @param $forwardToReplicas
     * @return void
     */
    public function saveRule($rule, $indexName, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->saveRule($rule, [
            'forwardToReplicas' => $forwardToReplicas,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $rules
     * @param $indexName
     * @return void
     */
    public function batchRules($rules, $indexName)
    {
        $index = $this->getIndex($indexName);
        $res = $index->saveRules($rules, [
            'forwardToReplicas'     => false,
            'clearExistingRules'    => false,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $indexName
     * @param $parameters
     * @return mixed
     */
    public function searchRules($indexName, $parameters)
    {
        $index = $this->getIndex($indexName);

        if (! isset($parameters['query'])) {
            $parameters['query'] = '';
        }

        return $index->searchRules($parameters['query'], $parameters);
    }

    /**
     * @param $indexName
     * @param $objectID
     * @param $forwardToReplicas
     * @return void
     */
    public function deleteRule($indexName, $objectID, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->deleteRule($objectID, [
            'forwardToReplicas' => $forwardToReplicas,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $indexName
     * @param $synonyms
     * @return void
     */
    public function setSynonyms($indexName, $synonyms)
    {
        $index = $this->getIndex($indexName);

        /**
         * Placeholders and alternative corrections are handled directly in Algolia dashboard.
         * To keep it working, we need to merge it before setting synonyms to Algolia indices.
         */
        $hitsPerPage = 100;
        $page = 0;
        do {
            $complexSynonyms = $index->searchSynonyms(
                '',
                [
                    'type'          => ['altCorrection1', 'altCorrection2', 'placeholder'],
                    'page'          => $page,
                    'hitsPerPage'   => $hitsPerPage,
                ]
            );

            foreach ($complexSynonyms['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $synonyms[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $complexSynonyms['nbHits']);

        if (!$synonyms) {
            $res = $index->clearSynonyms([
                'forwardToReplicas' => true,
            ]);
        } else {
            $res = $index->saveSynonyms($synonyms, [
                'forwardToReplicas'         => true,
                'replaceExistingSynonyms'   => true,
            ]);
        }

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $fromIndexName
     * @param $toIndexName
     * @return void
     */
    public function copySynonyms($fromIndexName, $toIndexName)
    {
        $fromIndex = $this->getIndex($fromIndexName);
        $toIndex = $this->getIndex($toIndexName);

        $synonymsToSet = [];

        $hitsPerPage = 100;
        $page = 0;
        do {
            $fetchedSynonyms = $fromIndex->searchSynonyms('', [
                'page' => $page,
                'hitsPerPage' => $hitsPerPage,
            ]);

            foreach ($fetchedSynonyms['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $synonymsToSet[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $fetchedSynonyms['nbHits']);

        if (!$synonymsToSet) {
            $res = $toIndex->clearSynonyms([
                'forwardToReplicas' => true,
            ]);
        } else {
            $res = $toIndex->saveSynonyms($synonymsToSet, [
                'forwardToReplicas'         => true,
                'replaceExistingSynonyms'   => true,
            ]);
        }

        self::setLastOperationInfo($toIndex, $res);
    }

    /**
     * @param $fromIndexName
     * @param $toIndexName
     *
     */
    public function copyQueryRules($fromIndexName, $toIndexName)
    {
        $res = $this->getClient()->copyRules($fromIndexName, $toIndexName, [
            'forwardToReplicas'  => false,
            'clearExistingRules' => true,
        ]);

        self::setLastOperationInfo($toIndexName, $res);
    }

    /**
     * @param $methodName
     * @return void
     * @throws AlgoliaException
     */
    protected function checkClient($methodName)
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
     * @param $indexName
     * @return void
     */
    public function clearIndex($indexName)
    {
        $res = $this->getIndex($indexName)->clearObjects();

        self::setLastOperationInfo($indexName, $res);
    }

    /**
     * @param $lastUsedIndexName
     * @param $lastTaskId
     * @return void
     * @throws AlgoliaException
     */
    public function waitLastTask($lastUsedIndexName = null, $lastTaskId = null)
    {
        if ($lastUsedIndexName === null && isset(self::$lastUsedIndexName)) {
            $lastUsedIndexName = self::$lastUsedIndexName;
            if ($lastUsedIndexName instanceof SearchIndex) {
                $lastUsedIndexName = $lastUsedIndexName->getIndexName();
            }
        }

        if ($lastTaskId === null && isset(self::$lastTaskId)) {
            $lastTaskId = self::$lastTaskId;
        }

        if (!$lastUsedIndexName || !$lastTaskId) {
            return;
        }

        $this->checkClient(__FUNCTION__);
        $this->client->initIndex($lastUsedIndexName)->waitTask($lastTaskId);
    }

    /**
     * @param $objects
     * @param $indexName
     * @return void
     * @throws \Exception
     */
    protected function prepareRecords(&$objects, $indexName)
    {
        $currentCET = new \DateTime('now', new \DateTimeZone('Europe/Paris'));
        $currentCET = $currentCET->format('Y-m-d H:i:s');

        $modifiedIds = [];
        foreach ($objects as $key => &$object) {
            $object['algoliaLastUpdateAtCET'] = $currentCET;

            $previousObject = $object;

            $object = $this->handleTooBigRecord($object);

            if ($object === false) {
                $longestAttribute = $this->getLongestAttribute($previousObject);
                $modifiedIds[] = $indexName . '
                    - ID ' . $previousObject['objectID'] . ' - skipped - longest attribute: ' . $longestAttribute;

                unset($objects[$key]);

                continue;
            } elseif ($previousObject !== $object) {
                $modifiedIds[] = $indexName . ' - ID ' . $previousObject['objectID'] . ' - truncated';
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
    protected function getMaxRecordSize()
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
    public function getLastIndexName()
    {
        return self::$lastUsedIndexName;
    }

    /**
     * @return string
     */
    public function getLastTaskId()
    {
        return self::$lastTaskId;
    }

    /**
     * @param $object
     *
     * @return int
     */
    protected function calculateObjectSize($object)
    {
        return mb_strlen(json_encode($object));
    }

    /**
     * @param $indexName
     * @param $q
     * @param $params
     * @return mixed|null
     */
    protected function searchWithDisjunctiveFaceting($indexName, $q, $params)
    {
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
            $disjunctiveQuery['indexName'] = $indexName;
            $disjunctiveQuery['query'] = $q;
            unset($disjunctiveQuery['disjunctiveFacets']);
        }

        // Merge facets and disjunctiveFacets for the hits query
        $facets = isset($params['facets']) ? $params['facets'] : [];
        $facets = array_merge($facets, $params['disjunctiveFacets']);
        unset($params['disjunctiveFacets']);

        // format the hits query for multipleQueries call
        $params['query'] = $q;
        $params['indexName'] = $indexName;
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