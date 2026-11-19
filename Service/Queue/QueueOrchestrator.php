<?php

namespace Algolia\AlgoliaSearch\Service\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Magento\Framework\Console\Cli;
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
     */
    public function runSequential(QueueReporterInterface $reporter): int
    {
        if (!$this->algoliaCredentialsManager->checkCredentialsWithSearchOnlyAPIKey()) {
            $this->algoliaCredentialsManager->displayErrorMessage(self::class);

            return Cli::RETURN_FAILURE;
        }

        try {
            $this->queue->runCron();
        } catch (Zend_Db_Exception $e) {
            $reporter->error($e->getMessage());

            return Cli::RETURN_FAILURE;
        }

        return Cli::RETURN_SUCCESS;
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
     */
    public function orchestrate(
        ?int $maxProcessesOverride,
        QueueReporterInterface $reporter
    ): int {
        if (!$this->lockManager->lock(self::ORCHESTRATOR_LOCK_NAME, 0)) {
            $reporter->warning('Indexing queue orchestration is already running, skipping.');

            return Cli::RETURN_SUCCESS;
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

                return Cli::RETURN_SUCCESS;
            }

            // Deliberately softer retrieval than worker mode's fail-fast
            // i.e. explicit store arguments fail hard when unknown, while store IDs
            // discovered from the queue must survive a deleted store for useful reporting
            // (falls back to plain ID labels when store is not found).
            $storeNames = $this->storeNameFetcher->resolveStoreNames($storeIds);
            $maxProcesses = $this->maxProcessesResolver->resolve($maxProcessesOverride, count($storeIds));
            $reporter->info(
                'Processing the indexing queue in parallel for ' . count($storeIds) . ' store(s) '
                . 'with up to ' . $maxProcesses . ' worker(s).'
            );

            $exitCodes = $this->workerProcessPool->run(
                storeIds: $storeIds,
                maxProcesses: $maxProcesses,
                reporter: $reporter,
                storeLabels: $this->getStoreLabels($storeIds, $storeNames),
                runId: $this->generateRunId()
            );

            return $this->printSummary($storeIds, $storeNames, $exitCodes, $reporter);
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
     * Per-store display label ("Store 2 (English)"), shared by worker output prefixing
     * and the run summary.
     *
     * @param array<int, string> $storeNames
     */
    protected function getStoreLabel(int $storeId, array $storeNames): string
    {
        return 'Store ' . $storeId . ' (' . ($storeNames[$storeId] ?? 'Store ID ' . $storeId) . ')';
    }

    /**
     * @param int[] $storeIds
     * @param array<int, string> $storeNames
     *
     * @return array<int, string> Labels keyed by store ID
     */
    protected function getStoreLabels(array $storeIds, array $storeNames): array
    {
        $labels = [];
        foreach ($storeIds as $storeId) {
            $labels[$storeId] = $this->getStoreLabel($storeId, $storeNames);
        }

        return $labels;
    }

    /**
     * @param int[] $storeIds
     * @param array<int, string> $storeNames
     * @param array<int, int> $exitCodes
     */
    protected function printSummary(
        array $storeIds,
        array $storeNames,
        array $exitCodes,
        QueueReporterInterface $reporter
    ): int {
        $hasFailure = false;
        sort($storeIds);

        foreach ($storeIds as $storeId) {
            $exitCode = $exitCodes[$storeId] ?? 1;
            $label = $this->getStoreLabel($storeId, $storeNames);

            if ($exitCode !== 0) {
                $hasFailure = true;
                $reporter->error($label . ': failed (exit code ' . $exitCode . ')');
            } else {
                $reporter->info($label . ': completed');
            }
        }

        return $hasFailure ? Cli::RETURN_FAILURE : Cli::RETURN_SUCCESS;
    }
}
