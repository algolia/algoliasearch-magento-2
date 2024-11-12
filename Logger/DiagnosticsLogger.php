<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Profiler;
use Monolog\Logger;

/**
 * This class provides a facade for both logging and profiling
 */
class DiagnosticsLogger
{
    protected bool $isLoggerEnabled = false;
    protected bool $isProfilerEnabled = true;

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

    public function start(string $action, bool $profileMethod = true): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->start($action);
        }

        if ($this->isProfilerEnabled && $profileMethod) {
            $timerName = $this->getCallingMethodName() ?: $action;
            if ($timerName) {
                Profiler::start($timerName, ['group' => 'algolia']);
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function stop(string $action, bool $profileMethod = false): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->stop($action);
        }

        if ($this->isProfilerEnabled && $profileMethod) {
            $timerName = $this->getCallingMethodName() ?: $action;
            if ($timerName) {
                Profiler::stop($timerName);
            }
        }
    }

    public function log(string $message, int $logLevel = Logger::INFO): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->log($message, $logLevel);
        }
    }

    public function error(string $message): void {
        if ($this->isLoggerEnabled) {
            $this->logger->log($message, Logger::ERROR);
        }
    }

    /**
     * Gets the name of the method that called the diagnostics
     *
     * @return string|null
     */
    protected function getCallingMethodName(): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3);
        $level = 2;
        return array_key_exists($level, $backtrace)
            ? $backtrace[$level]['class'] . "::" . $backtrace[$level]['function']
            : null;
    }
}
