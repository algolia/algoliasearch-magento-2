<?php

namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Logger\CronLoggerOutput;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
use Symfony\Component\Console\Output\NullOutput;

class ProcessQueue
{
    public function __construct(
        protected QueueHelper $queueHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected QueueOrchestrator $queueOrchestrator,
        protected CronLoggerOutput $cronLoggerOutput,
        protected NullOutput $nullOutput,
    ) {}

    public function execute()
    {
        if (!$this->queueHelper->isQueueActive() || !$this->queueHelper->useBuiltInCron()) {
            return;
        }

        if (!$this->queueHelper->isParallelProcessingEnabled()) {
            // Legacy sequential path, unchanged behavior
            if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
                $this->algoliaCredentialsManager->displayErrorMessage(self::class);

                return;
            }

            $this->queue->runCron();

            return;
        }

        $this->queueOrchestrator->orchestrate(null, $this->cronLoggerOutput, $this->nullOutput);
    }
}
