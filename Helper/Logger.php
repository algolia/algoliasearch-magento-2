<?php

namespace Algolia\AlgoliaSearch\Helper;

use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

class Logger
{
    private $enabled;
    private $config;
    private $logger;

    private $timers = [];
    private $stores = [];

    public function __construct(
        StoreManagerInterface $storeManager,
        ConfigHelper $configHelper,
        LoggerInterface $logger
    ) {
        $this->config = $configHelper;
        $this->enabled = $this->config->isLoggingEnabled();
        $this->logger = $logger;

        foreach ($storeManager->getStores() as $store) {
            $this->stores[$store->getId()] = $store->getName();
        }
    }

    public function isEnable()
    {
        return $this->enabled;
    }

    public function getStoreName($storeId)
    {
        if ($storeId === null || !isset($this->stores[$storeId])) {
            return 'undefined store';
        }

        return $storeId . ' (' . $this->stores[$storeId] . ')';
    }

    public function start($action)
    {
        if ($this->enabled === false) {
            return;
        }

        $this->log('');
        $this->log('');
        $this->log('>>>>> BEGIN ' . $action);
        $this->timers[$action] = microtime(true);
    }

    public function stop($action)
    {
        if ($this->enabled === false) {
            return;
        }

        if (false === isset($this->timers[$action])) {
            throw new \Exception('Algolia Logger => non existing action');
        }

        $this->log('<<<<< END ' . $action . ' (' . $this->formatTime($this->timers[$action], microtime(true)) . ')');
    }

    public function log($message)
    {
        if ($this->enabled) {
            $this->logger->info($message);
        }
    }

    public function error($message) {
        if ($this->enabled) {
            $this->logger->error($message);
        }
    }

    private function formatTime($begin, $end)
    {
        return ($end - $begin) . 'sec';
    }

}
