<?php

namespace Algolia\AlgoliaSearch\Service\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Lock\LockManagerInterface;
use Zend_Db_Exception;

/**
 * Shared sequential/parallel queue processing entry point, used by both the
 * algolia:queue:process command and the built-in cron job.
 *
 * ORCHESTRATOR MODE
 * Discovers the stores with pending jobs and spawns one worker per store, up to the
 * resolved concurrency cap, then reports a per-store summary.
 */
class QueueOrchestrator
{
    public const string ORCHESTRATOR_LOCK_NAME = 'algolia_queue_process';

    public function __construct(
        protected Queue                     $queue,
        protected LockManagerInterface      $lockManager,
        protected MaxProcessesResolver      $maxProcessesResolver,
        protected WorkerProcessPool         $workerProcessPool,
        protected StoreNameFetcher          $storeNameFetcher,
        protected AlgoliaCredentialsManager $algoliaCredentialsManager,
    ) {}

    /**
     * Sequential path: whole-queue processing with a global credentials check.
     *
     * @return bool Reports success of operation
     *              False when the credentials check failed or the queue run threw
     */
    public function runSequential(QueueReporterInterface $reporter): bool
    {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return false;
        }

        try {
            $this->queue->runCron();
        } catch (Zend_Db_Exception $e) {
            $reporter->error($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Discovers the stores with pending jobs and spawns one worker per store, up to the
     * resolved concurrency cap, then reports a per-store summary. Children gate themselves
     * per store (credentials, per-store lock), so no global credentials check happens here:
     * a default-scope check would false-positive when stores are configured individually.
     *
     * $reporter is the single output funnel: it receives both the orchestrator's own lines
     * (info/warning/error) and the streamed stdout/stderr of the spawned worker processes
     * (relayed through log()).
     *
     * @return bool Reports success of operation
     *              False when any store's worker failed.
     *              A run that found nothing to do, or that found the orchestration lock
     *              already held, is a success.
     */
    public function orchestrate(
        ?int $maxProcessesOverride,
        QueueReporterInterface $reporter
    ): bool {
        if (!$this->lockManager->lock(self::ORCHESTRATOR_LOCK_NAME, 0)) {
            $reporter->warning('Indexing queue orchestration is already running, skipping.');

            return true;
        }

        try {
            // Sanity check for unexpected job shape - not supported for parallel indexing
            if ($this->queue->hasStorelessPendingJobs()) {
                $reporter->warning(
                    'Store-agnostic indexing jobs are pending, falling back to sequential '
                    . 'processing to drain them.'
                );

                return $this->runSequential($reporter);
            }

            $storeIds = $this->queue->discoverPendingStoreIds();

            if (!$storeIds) {
                $reporter->info('No pending indexing queue jobs.');

                return true;
            }

            $maxProcesses = $this->maxProcessesResolver->resolve($maxProcessesOverride, count($storeIds));
            $reporter->info(
                'Processing the indexing queue in parallel for ' . count($storeIds) . ' store(s) '
                . 'with up to ' . $maxProcesses . ' worker(s).'
            );

            $exitCodes = $this->workerProcessPool->run(
                storeIds: $storeIds,
                maxProcesses: $maxProcesses,
                reporter: $reporter,
                runId: $this->generateRunId()
            );

            return $this->printSummary($storeIds, $exitCodes, $reporter);
        } finally {
            $this->lockManager->unlock(self::ORCHESTRATOR_LOCK_NAME);
        }
    }

    /**
     * One run ID per orchestration tick: every worker's queue log row carries it,
     * so the tick's rows correlate across stores.
     */
    protected function generateRunId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * The one place the worker process exit codes become a run-level verdict. A store with no
     * recorded exit code (a worker that never reported one) counts as a failure.
     *
     * @param int[] $storeIds
     * @param array<int, int> $exitCodes
     *
     * @return bool Reports success of operation
     *              False when any store's worker exited non-zero
     */
    protected function printSummary(
        array $storeIds,
        array $exitCodes,
        QueueReporterInterface $reporter
    ): bool {
        $hasFailure = false;
        sort($storeIds);

        foreach ($storeIds as $storeId) {
            $exitCode = $exitCodes[$storeId] ?? 1;
            $label = $this->storeNameFetcher->getStoreLabel($storeId);

            if ($exitCode !== 0) {
                $hasFailure = true;
                $reporter->error($label . ': failed (exit code ' . $exitCode . ')');
            } else {
                $reporter->info($label . ': completed');
            }
        }

        return !$hasFailure;
    }
}
