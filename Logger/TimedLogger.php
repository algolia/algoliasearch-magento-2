<?php

namespace Algolia\AlgoliaSearch\Logger;

use Algolia\AlgoliaSearch\Exceptions\AlgoliaException;
use Monolog\Logger;

class TimedLogger
{
    /** @var string[]  */
    protected array $timers = [];

    public function __construct(
        protected AlgoliaLogger $logger
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
     * @throws AlgoliaException
     */
    public function stop($action): void
    {
        if (false === isset($this->timers[$action])) {
            throw new AlgoliaException('Algolia Logger => non existing action');
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
