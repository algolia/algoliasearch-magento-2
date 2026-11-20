<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;

/**
 * Runs one worker per store ID, up to a concurrency cap, refilling the pool as workers
 * exit. Symfony Process has no "wait for any" API, so the pool polls and refills;
 * owns spawning, output relay and exit code collection only - the workers surface their
 * own structured, prefixed lines. No locks, no discovery, no mode decisions, etc.
 */
class WorkerProcessPool
{
    protected const int POLL_INTERVAL_MICROSECONDS = 100000;

    public function __construct(
        protected WorkerProcessFactory $workerProcessFactory,
    ) {
    }

    /**
     * @param int[] $storeIds
     * @param string|null $runId Correlation ID forwarded to every spawned worker's --run-id option
     *
     * @return array<int, int> Exit codes keyed by store ID (killed workers count as failures)
     */
    public function run(
        array $storeIds,
        int $maxProcesses,
        QueueReporterInterface $reporter,
        ?string $runId = null
    ): array {
        $pendingStoreIds = array_values($storeIds);
        $workers = [];
        $exitCodes = [];

        while ($pendingStoreIds && count($workers) < $maxProcesses) {
            $workers[] = $this->startWorker(array_shift($pendingStoreIds), $runId);
        }

        while ($workers) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            /**
             * @var WorkerProcess $worker
             */
            foreach ($workers as $index => $worker) {
                $this->relayLines($worker, $reporter);

                if ($worker->isRunning()) {
                    continue;
                }

                // The worker may have terminated just after readLines() saw it running:
                // one more pass flushes any partial line it produced on the way out
                $this->relayLines($worker, $reporter);

                $exitCodes[$worker->getStoreId()] = $worker->getEffectiveExitCode();
                unset($workers[$index]);

                if ($pendingStoreIds) { //keep filling as workers free up
                    $workers[] = $this->startWorker(array_shift($pendingStoreIds), $runId);
                }
            }
        }

        return $exitCodes;
    }

    protected function startWorker(int $storeId, ?string $runId = null): WorkerProcess
    {
        $worker = $this->workerProcessFactory->create($storeId, $runId);
        $worker->start();

        return $worker;
    }

    protected function relayLines(WorkerProcess $worker, QueueReporterInterface $reporter): void
    {
        foreach ($worker->readLines() as $line) {
            $reporter->log($line);
        }
    }
}
