<?php

namespace Algolia\AlgoliaSearch\Helper;

use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Client;
use AlgoliaSearch\Version;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Message\ManagerInterface;
use Symfony\Component\Console\Output\ConsoleOutput;

class AlgoliaHelper extends AbstractHelper
{
    /** @var Client */
    private $client;

    /** @var ConfigHelper */
    private $config;

    /** @var ManagerInterface */
    private $messageManager;

    /** @var ConsoleOutput */
    private $consoleOutput;

    /** @var int */
    private $maxRecordSize = 20000;

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

        Version::addPrefixUserAgentSegment('Magento2 integration', $this->config->getExtensionVersion());
        Version::addSuffixUserAgentSegment('PHP', phpversion());
        Version::addSuffixUserAgentSegment('Magento', $this->config->getMagentoVersion());
        Version::addSuffixUserAgentSegment('Edition', $this->config->getMagentoEdition());
    }

    public function getRequest()
    {
        return $this->_getRequest();
    }

    public function resetCredentialsFromConfig()
    {
        if ($this->config->getApplicationID() && $this->config->getAPIKey()) {
            $this->client = new Client($this->config->getApplicationID(), $this->config->getAPIKey());
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

        return $this->client->listIndexes();
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

    public function setSettings($indexName, $settings, $forwardToReplicas = false, $mergeSettings = false)
    {
        $this->checkClient(__FUNCTION__);

        $index = $this->getIndex($indexName);

        if ($mergeSettings === true) {
            $settings = $this->mergeSettings($indexName, $settings);
        }

        $res = $index->setSettings($settings, $forwardToReplicas);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function deleteIndex($indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->deleteIndex($indexName);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function deleteObjects($ids, $indexName)
    {
        $this->checkClient(__FUNCTION__);

        $index = $this->getIndex($indexName);

        $res = $index->deleteObjects($ids);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function moveIndex($tmpIndexName, $indexName)
    {
        $this->checkClient(__FUNCTION__);
        $res = $this->client->moveIndex($tmpIndexName, $indexName);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function generateSearchSecuredApiKey($key, $params = [])
    {
        return $this->client->generateSecuredApiKey($key, $params);
    }

    public function getSettings($indexName)
    {
        return $this->getIndex($indexName)->getSettings();
    }

    public function mergeSettings($indexName, $settings)
    {
        $onlineSettings = [];

        try {
            $onlineSettings = $this->getSettings($indexName);
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
            $res = $index->partialUpdateObjects($objects);
        } else {
            $res = $index->addObjects($objects);
        }

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function saveRule($rule, $indexName, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->saveRule($rule['objectID'], $rule, $forwardToReplicas);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function batchRules($rules, $indexName)
    {
        $index = $this->getIndex($indexName);
        $res = $index->batchRules($rules, false, false);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function searchRules($indexName, $parameters)
    {
        $index = $this->getIndex($indexName);

        return $index->searchRules($parameters);
    }

    public function deleteRule($indexName, $objectID, $forwardToReplicas = false)
    {
        $index = $this->getIndex($indexName);
        $res = $index->deleteRule($objectID, $forwardToReplicas);

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function setSynonyms($indexName, $synonyms)
    {
        $index = $this->getIndex($indexName);

        /**
         * Placeholders and alternative corrections are handled directly in Algolia dashboard.
         * To keep it works, we need to merge it before setting synonyms to Algolia indices.
         */
        $hitsPerPage = 100;
        $page = 0;
        do {
            $complexSynonyms = $index->searchSynonyms(
                '',
                ['altCorrection1', 'altCorrection2', 'placeholder'],
                $page,
                $hitsPerPage
            );

            foreach ($complexSynonyms['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $synonyms[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $complexSynonyms['nbHits']);

        if (!$synonyms) {
            $res = $index->clearSynonyms(true);
        } else {
            $res = $index->batchSynonyms($synonyms, true, true);
        }

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function copySynonyms($fromIndexName, $toIndexName)
    {
        $fromIndex = $this->getIndex($fromIndexName);
        $toIndex = $this->getIndex($toIndexName);

        $synonymsToSet = [];

        $hitsPerPage = 100;
        $page = 0;
        do {
            $fetchedSynonyms = $fromIndex->searchSynonyms('', [], $page, $hitsPerPage);
            foreach ($fetchedSynonyms['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $synonymsToSet[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $fetchedSynonyms['nbHits']);

        if (!$synonymsToSet) {
            $res = $toIndex->clearSynonyms(true);
        } else {
            $res = $toIndex->batchSynonyms($synonymsToSet, true, true);
        }

        self::$lastUsedIndexName= $toIndex;
        self::$lastTaskId = $res['taskID'];
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
            $fetchedQueryRules = $fromIndex->searchRules([
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
            $res = $toIndex->clearRules(true);
        } else {
            $res = $toIndex->batchRules($queryRulesToSet, true, true);
        }

        self::$lastUsedIndexName= $toIndex;
        self::$lastTaskId = $res['taskID'];
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
        $res = $this->getIndex($indexName)->clearIndex();

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function waitLastTask($lastUsedIndexName = null, $lastTaskId = null)
    {
        if ($lastUsedIndexName === null && isset(self::$lastUsedIndexName)) {
            $lastUsedIndexName = self::$lastUsedIndexName;
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

    private function handleTooBigRecord($object)
    {
        $size = mb_strlen(json_encode($object));

        if ($size > $this->maxRecordSize) {
            foreach ($this->potentiallyLongAttributes as $attribute) {
                if (isset($object[$attribute])) {
                    unset($object[$attribute]);
                }
            }

            $size = mb_strlen(json_encode($object));

            if ($size > $this->maxRecordSize) {
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
            $attributeLenght = mb_strlen(json_encode($value));

            if ($attributeLenght > $maxLength) {
                $longestAttribute = $attribute;

                $maxLength = $attributeLenght;
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
}
