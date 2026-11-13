<?php

namespace Algolia\AlgoliaSearch\Service\Queue\Output;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Algolia\AlgoliaSearch\Api\QueueReporterInterface;

class LoggerQueueReporter implements QueueReporterInterface
{
    public function __construct(
        protected LoggerInterface $logger
    ) {}

    public function info(string $message): void
    {
        $this->logger->info($message);
    }

    public function warning(string $message): void
    {
        $this->logger->warning($message);
    }

    public function error(string $message): void
    {
        $this->logger->error($message);
    }

    public function log(string $message): void
    {
        $this->logger->info($message);
    }
}
