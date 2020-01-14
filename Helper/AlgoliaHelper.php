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
    private $client;

    /** @var ConfigHelper */
    private $config;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var ConsoleOutput */
    private $consoleOutput;

    /** @var int */
    private $maxRecordSize;

    /** @var array */
    private $potentiallyLongAttributes = ['description', 'short_description', 'meta_description', 'content'];

    /** @var array */
    private $nonCastableAttributes = ['sku', 'name', 'description'];

    /** @var string */
    private static $lastUsedIndexName;

    /** @var string */
    private static $lastTaskId;

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

    public function getRequest()
    {
        return $this->_getRequest();
    }

    public function resetCredentialsFromConfig()
    {
        if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
            $this->client = SearchClient::create(
                $this->config->getApplicationID(),
                $this->config->getAPIKey()
            );
        }
    }

    public function getClient()
    {
        $this->checkClient(__FUNCTION__);

        return $this->client;
    }

    public function getIndex($name)
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->initIndex($name);
    }

    public function listIndexes()
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->listIndices();
    }

    public function query($indexName, $q, $params)
    {
        $this->checkClient(__FUNCTION__);

        return $this->client->initIndex($indexName)->search($q, $params);
    }

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

    public function deleteIndex($indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->initIndex($indexName)->delete();

        self::setLastOperationInfo($indexName, $res);
    }

    public function deleteObjects($ids, $indexName)
    {
        $this->checkClient(__FUNCTION__);

        $index = $this->getIndex($indexName);

        $res = $index->deleteObjects($ids);

        self::setLastOperationInfo($indexName, $res);
    }

    public function moveIndex($tmpIndexName, $indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->moveIndex($tmpIndexName, $indexName);

        self::setLastOperationInfo($indexName, $res);
    }

    public function generateSearchSecuredApiKey($key, $params = [])
    {
        // This is to handle a difference between API client v1 and v2.
        if (! isset($params['tagFilters'])) {
            $params['tagFilters'] = '';
        }

        return SearchClient::generateSecuredApiKey($key, $params);
    }

    public function getSettings($indexName)
    {
        return $this->getIndex($indexName)->getSettings();
    }

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

    private static function setLastOperationInfo($indexName, $response)
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

    public function saveRule($rule, $indexName, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->saveRule($rule, [
            'forwardToReplicas' => $forwardToReplicas,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    public function batchRules($rules, $indexName)
    {
        $index = $this->getIndex($indexName);
        $res = $index->saveRules($rules, [
            'forwardToReplicas'     => false,
            'clearExistingRules'    => false,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

    public function searchRules($indexName, $parameters)
    {
        $index = $this->getIndex($indexName);

        if (! isset($parameters['query'])) {
            $parameters['query'] = '';
        }

        return $index->searchRules($parameters['query'], $parameters);
    }

    public function deleteRule($indexName, $objectID, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->deleteRule($objectID, [
            'forwardToReplicas' => $forwardToReplicas,
        ]);

        self::setLastOperationInfo($indexName, $res);
    }

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
     * @throws AlgoliaException
     */
    public function copyQueryRules($fromIndexName, $toIndexName)
    {
        $fromIndex = $this->getIndex($fromIndexName);
        $toIndex = $this->getIndex($toIndexName);

        $queryRulesToSet = [];

        $hitsPerPage = 100;
        $page = 0;
        do {
            $fetchedQueryRules = $fromIndex->searchRules('', [
                'page' => $page,
                'hitsPerPage' => $hitsPerPage,
            ]);

            foreach ($fetchedQueryRules['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $queryRulesToSet[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $fetchedQueryRules['nbHits']);

        if (!$queryRulesToSet) {
            $res = $toIndex->clearRules([
                'forwardToReplicas' => true,
            ]);
        } else {
            $res = $toIndex->saveRules($queryRulesToSet, [
                'forwardToReplicas'  => true,
                'clearExistingRules' => true,
            ]);
        }

        self::setLastOperationInfo($toIndex, $res);
    }

    private function checkClient($methodName)
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

    public function clearIndex($indexName)
    {
        $res = $this->getIndex($indexName)->clearObjects();

        self::setLastOperationInfo($indexName, $res);
    }

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

    private function prepareRecords(&$objects, $indexName)
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

    private function getMaxRecordSize()
    {
        if (!$this->maxRecordSize) {
            $this->maxRecordSize = $this->config->getMaxRecordSizeLimit()
                ? $this->config->getMaxRecordSizeLimit() : $this->config->getDefaultMaxRecordSize();
        }

        return $this->maxRecordSize;
    }

    private function handleTooBigRecord($object)
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

    private function getLongestAttribute($object)
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

    public function castProductObject(&$productData)
    {
        foreach ($productData as $key => &$data) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }

            $data = $this->castAttribute($data);

            if (is_array($data) === false) {
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

    private function castRecord($object)
    {
        foreach ($object as $key => &$value) {
            if (in_array($key, $this->nonCastableAttributes, true) === true) {
                continue;
            }

            $value = $this->castAttribute($value);
        }

        return $object;
    }

    private function castAttribute($value)
    {
        if (is_numeric($value) && floatval($value) === floatval((int) $value)) {
            return (int) $value;
        }

        if (is_numeric($value)) {
            return floatval($value);
        }

        return $value;
    }

    public function getLastIndexName()
    {
        return self::$lastUsedIndexName;
    }

    public function getLastTaskId()
    {
        return self::$lastTaskId;
    }

    /**
     * @param $object
     *
     * @return int
     */
    private function calculateObjectSize($object)
    {
        return mb_strlen(json_encode($object));
    }
}
