<?php

namespace Algolia\AlgoliaSearch\Helper;

use AlgoliaSearch\AlgoliaException;
use AlgoliaSearch\Client;
use Magento\Framework\Message\ManagerInterface;

class AlgoliaHelper
{
    /** @var Client */
    protected $client;
    protected $config;
    protected $messageManager;

    public function __construct(ConfigHelper $configHelper, ManagerInterface $messageManager)
    {
        $this->messageManager = $messageManager;
        $this->config = $configHelper;
        
        $this->resetCredentialsFromConfig();
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

        $index->setSettings($settings);
    }

    public function deleteIndex($index_name)
    {
        $this->checkClient(__FUNCTION__);
        $this->client->deleteIndex($index_name);
    }

    public function deleteObjects($ids, $index_name)
    {
        $index = $this->getIndex($index_name);

        $index->deleteObjects($ids);
    }

    public function moveIndex($index_name_tmp, $index_name)
    {
        $this->checkClient(__FUNCTION__);
        $this->client->moveIndex($index_name_tmp, $index_name);
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

        $removes = ['slaves'];

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
                        $ids[$index_name.' objectID('.$object['objectID'].')'] = true;
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
            $this->messageManager->addError('Algolia reindexing : You have some records ('.implode(',', array_keys($ids)).') that are too big. They have either been truncated or skipped');
        }
    }

    public function addObjects($objects, $index_name)
    {
        $this->handleTooBigRecords($objects, $index_name);

        $index = $this->getIndex($index_name);

        if ($this->config->isPartialUpdateEnabled()) {
            $index->partialUpdateObjects($objects);
        } else {
            $index->addObjects($objects);
        }
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
            $index->clearSynonyms(true);
            return;
        }

        $index->batchSynonyms($synonyms, true, true);
    }

    private function checkClient($methodName)
    {
        if (isset($this->client))
        {
            return;
        }

        $this->resetCredentialsFromConfig();


        if (!isset($this->client))
        {
            throw new AlgoliaException('Operation "'.$methodName.' could not be performed because Algolia credetials were not provided.');
        }
    }
}
