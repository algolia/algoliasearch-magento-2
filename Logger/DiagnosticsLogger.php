<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;

class DiagnosticsLogger
{
    /** @var string[]  */
    protected array $timers = [];
    protected bool $enabled = false;

    public function __construct(
        protected ConfigHelper     $config,
        protected AlgoliaLogger    $logger,
        protected StoreNameFetcher $storeNameFetcher
    ) {
        $this->enabled = $this->config->isLoggingEnabled();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @throws NoSuchEntityException
     */
    public function getStoreName($storeId): string
    {
        return $storeId . ' (' . $this->storeNameFetcher->getStoreName($storeId) . ')';
    }

    public function start($action): void
    {
        if ($this->enabled === false) {
            return;
        }

        $this->log('');
        $this->log('');
        $this->log('>>>>> BEGIN ' . $action);
        $this->timers[$action] = microtime(true);
    }

    public function stop($action): void
    {
        if ($this->enabled === false) {
            return;
        }

        if (false === isset($this->timers[$action])) {
            throw new \Exception('Algolia Logger => non existing action');
        }

        $this->log('<<<<< END ' . $action . ' (' . $this->formatTime($this->timers[$action], microtime(true)) . ')');
    }

    public function log($message): void
    {
        if ($this->enabled) {
            $this->logger->info($message);
        }
    }

    public function error($message): void {
        if ($this->enabled) {
            $this->logger->error($message);
        }
    }

    private function formatTime($begin, $end): string
    {
        return ($end - $begin) . 'sec';
    }

}
