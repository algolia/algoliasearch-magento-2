<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;
use Monolog\Logger;

/**
 * This class provides a facade for both logging and profiling
 */
class DiagnosticsLogger
{
    protected bool $isLoggerEnabled = false;
    protected bool $isProfilerEnabled = false;

    public function __construct(
        protected ConfigHelper     $config,
        protected TimedLogger      $logger,
        protected StoreNameFetcher $storeNameFetcher
    ) {
        $this->isLoggerEnabled = $this->config->isLoggingEnabled();
    }

    public function isLoggerEnabled(): bool
    {
        return $this->isLoggerEnabled;
    }

    public function isProfilerEnabled(): bool
    {
        return $this->isProfilerEnabled;
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
        if (!$this->isLoggerEnabled()) {
            return;
        }

        $this->logger->start($action);
    }

    /**
     * @throws \Exception
     */
    public function stop($action): void
    {
        if (!$this->isLoggerEnabled()) {
            return;
        }

        $this->logger->stop($action);
    }

    public function log(string $message, int $logLevel = Logger::INFO): void
    {
        if ($this->isLoggerEnabled()) {
            $this->logger->log($message, $logLevel);
        }
    }

    public function error($message): void {
        if ($this->isLoggerEnabled()) {
            $this->logger->log($message, Logger::ERROR);
        }
    }
}
