<?php

namespace Algolia\AlgoliaSearch\Cron;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\Output\ReporterWorkerOutputFactory;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;

class ProcessQueue
{
    public function __construct(
        protected QueueHelper $queueHelper,
        protected Queue $queue,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
        protected QueueOrchestrator $queueOrchestrator,
        protected QueueReporterInterface $queueReporter,
        protected ReporterWorkerOutputFactory $reporterWorkerOutputFactory,
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

        // Worker output is funneled through the cron's own reporter via the adapter,
        // so prefixed child lines land in algolia.log like the orchestrator's own lines
        $workerOutput = $this->reporterWorkerOutputFactory->create(['reporter' => $this->queueReporter]);

        $this->queueOrchestrator->orchestrate(null, $this->queueReporter, $workerOutput);
    }
}
