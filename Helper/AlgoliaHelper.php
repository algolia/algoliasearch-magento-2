<?php

namespace Algolia\AlgoliaSearch\Helper;

use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Client;
use AlgoliaSearch\Version;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Message\ManagerInterface;

class AlgoliaHelper extends AbstractHelper
{
    /** @var Client */
    protected $client;
    protected $config;
    protected $messageManager;

    /** @var string */
    private static $lastUsedIndexName;

    /** @var int */
    private static $lastTaskId;

    public function __construct(Context $context, ConfigHelper $configHelper, ManagerInterface $messageManager)
    {
        parent::__construct($context);

        $this->messageManager = $messageManager;
        $this->config = $configHelper;

        $this->resetCredentialsFromConfig();

        Version::addPrefixUserAgentSegment('Magento2 integration', $this->config->getExtensionVersion());
        Version::addSuffixUserAgentSegment('PHP', phpversion());
        Version::addSuffixUserAgentSegment('Magento', $this->config->getMagentoVersion());
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

    public function query($index_name, $q, $params)
    {
        $this->checkClient(__FUNCTION__);
        return $this->client->initIndex($index_name)->search($q, $params);
    }

    public function setSettings($indexName, $settings)
    {
        $index = $this->getIndex($indexName);

        $res = $index->setSettings($settings);

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

    public function mergeSettings($index_name, $settings)
    {
        $onlineSettings = [];

        try {
            $onlineSettings = $this->getIndex($index_name)->getSettings();
        } catch (\Exception $e) {
        }

        $removes = ['slaves', 'replicas'];

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

    public function handleTooBigRecords(&$objects, $index_name)
    {
        $long_attributes = ['description', 'short_description', 'meta_description', 'content'];

        $good_size = true;

        $ids = [];

        foreach ($objects as $key => &$object) {
            $size = mb_strlen(json_encode($object));

            if ($size > 20000) {
                foreach ($long_attributes as $attribute) {
                    if (isset($object[$attribute])) {
                        unset($object[$attribute]);
                        $ids[$index_name . ' objectID(' . $object['objectID'] . ')'] = true;
                        $good_size = false;
                    }
                }

                $size = mb_strlen(json_encode($object));

                if ($size > 20000) {
                    unset($objects[$key]);
                }
            }
        }

        if (count($objects) <= 0) {
            return;
        }

        if ($good_size === false) {
            $this->messageManager->addError('Algolia reindexing : You have some records (' . implode(',', array_keys($ids)) . ') that are too big. They have either been truncated or skipped');
        }
    }

    public function addObjects($objects, $indexName)
    {
        $this->handleTooBigRecords($objects, $indexName);

        $index = $this->getIndex($indexName);

        if ($this->config->isPartialUpdateEnabled()) {
            $res = $index->partialUpdateObjects($objects);
        } else {
            $res = $index->addObjects($objects);
        }

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
            $complexSynonyms = $index->searchSynonyms('', ['altCorrection1', 'altCorrection2', 'placeholder'], $page, $hitsPerPage);
            foreach ($complexSynonyms['hits'] as $hit) {
                unset($hit['_highlightResult']);

                $synonyms[] = $hit;
            }

            $page++;
        } while (($page * $hitsPerPage) < $complexSynonyms['nbHits']);

        if (empty($synonyms)) {
            $res = $index->clearSynonyms(true);
        }
        else {
            $res = $index->batchSynonyms($synonyms, true, true);
        }

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    private function checkClient($methodName)
    {
        if (isset($this->client)) {
            return;
        }

        $this->resetCredentialsFromConfig();

        if (!isset($this->client)) {
            throw new AlgoliaException('Operation "' . $methodName . ' could not be performed because Algolia credentials were not provided.');
        }
    }

    public function clearIndex($indexName)
    {
        $res = $this->getIndex($indexName)->clearIndex();

        self::$lastUsedIndexName = $indexName;
        self::$lastTaskId = $res['taskID'];
    }

    public function waitLastTask()
    {
        if (!isset(self::$lastUsedIndexName) || !isset(self::$lastTaskId)) {
            return;
        }

        $this->resetCredentialsFromConfig();
        $this->client->initIndex(self::$lastUsedIndexName)->waitTask(self::$lastTaskId);
    }
}
