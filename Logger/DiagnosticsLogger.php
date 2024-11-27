<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
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
    /** @var array */
    protected const ALGOLIA_TAGS = ['group' => 'algolia'];
    protected const PROFILE_LOG_MESSAGES_DEFAULT = false;
    protected const DEFAULT_NAMESPACE_DEPTH = 2;

    protected bool $isLoggerEnabled = false;
    protected bool $isProfilerEnabled = false;

    /** @var string[]  */
    private array $timerStack = [];

    public function __construct(
        protected ConfigHelper     $config,
        protected TimedLogger      $logger,
        protected StoreNameFetcher $storeNameFetcher
    )
    {
        $this->isLoggerEnabled = $this->config->isLoggingEnabled();
        $this->isProfilerEnabled = $this->config->isProfilerEnabled();
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

    public function start(string $action, bool $profileMethod = self::PROFILE_LOG_MESSAGES_DEFAULT): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->start($action);
        }

        if ($this->isProfilerEnabled && $profileMethod) {
            $timerName = $this->getCallingMethodName() ?: $action;
            if ($timerName) {
                $this->startProfiling($timerName);
            }
        }
    }


    /**
     * @throws DiagnosticsException
     */
    public function stop(string $action, bool $profileMethod = self::PROFILE_LOG_MESSAGES_DEFAULT): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->stop($action);
        }

        if ($this->isProfilerEnabled && $profileMethod) {
            $timerName = $this->getCallingMethodName() ?: $action;
            if ($timerName) {
                $this->stopProfiling($timerName);
            }
        }
    }

    public function startProfiling(string $timerName): void
    {
        if (!$this->isProfilerEnabled) return;

        $timerName = $this->simplifyMethodName($timerName);
        $this->timerStack[] = $timerName;

        Profiler::start($timerName, self::ALGOLIA_TAGS);
    }

    /**
     * @throws DiagnosticsException
     */
    public function stopProfiling(string $timerName): void
    {
        if (!$this->isProfilerEnabled) return;

        $lastTimerName = array_pop($this->timerStack); //$this->timerStack[count($this->timerStack) - 1];
        $timerName = $this->simplifyMethodName($timerName);

        if ($lastTimerName !== $timerName) {
            throw new DiagnosticsException(__("Profiling on %1 was stopped before nested operation %2 completed.", $timerName, $lastTimerName));
        }

        Profiler::setDefaultTags(self::ALGOLIA_TAGS);
        Profiler::stop($timerName);
        Profiler::setDefaultTags([]);
    }

    public function log(string $message, int $logLevel = Logger::INFO): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->log($message, $logLevel);
        }
    }

    public function error(string $message): void
    {
        if ($this->isLoggerEnabled) {
            $this->logger->log($message, Logger::ERROR);
        }
    }

    /**
     * Gets the name of the method that called the diagnostics
     *
     * @param int $level
     * @return string|null
     */
    protected function getCallingMethodName(int $level = 2): ?string
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, $level + 1);
        return array_key_exists($level, $backtrace)
            ? $backtrace[$level]['class'] . "::" . $backtrace[$level]['function']
            : null;
    }

    protected function simplifyMethodName(string $methodName, int $namespaceDepth = self::DEFAULT_NAMESPACE_DEPTH): string
    {
        $separator = '\\';
        $parts = explode($separator, $methodName);

        if (count($parts) <= abs($namespaceDepth)) {
            return $methodName;
        }

        $simplified = implode($separator, array_slice($parts, $namespaceDepth));
        if (!count($this->timerStack)) {
            $simplified = "ALGOLIA:" . $simplified;
        }
        return $simplified;
    }

}
