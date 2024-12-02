<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Exception\DiagnosticsException;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class TimedLogger
{
    /** @var string[]  */
    protected array $timers = [];

    public function __construct(
        protected LoggerInterface $logger
    )
    {}

    public function start($action): void
    {
        $this->log('');
        $this->log('');
        $this->log('>>>>> BEGIN ' . $action);
        $this->timers[$action] = microtime(true);
    }

    /**
     * @throws DiagnosticsException
     */
    public function stop($action): void
    {
        if (false === isset($this->timers[$action])) {
            throw new DiagnosticsException(__('Algolia Logger => non existing action'));
        }

        $this->log('<<<<< END ' . $action . ' (' . $this->formatTime($this->timers[$action], microtime(true)) . ')');
    }

    public function log(string $message, int $logLevel = Logger::INFO): void
    {
        $this->logger->log($logLevel, $message);
    }

    private function formatTime($begin, $end): string
    {
        return ($end - $begin) . 'sec';
    }

}
