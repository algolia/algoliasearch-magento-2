<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\Output\StorePrefixedOutput;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs one worker per store ID, up to a concurrency cap, refilling the pool as workers
 * exit. Symfony Process has no "wait for any" API, so the pool polls and refills;
 * Owns spawning, streaming (with per-store line prefixing) and exit code collection
 * only - no locks, no discovery, no mode decisions, etc.
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
     * @param array<int, string> $storeLabels Per-store display labels keyed by store ID
     * @param string|null $runId Correlation ID forwarded to every spawned worker's --run-id option
     *
     * @return array<int, int> Exit codes keyed by store ID (killed workers count as failures)
     */
    public function run(
        array $storeIds,
        int $maxProcesses,
        OutputInterface $output,
        array $storeLabels = [],
        ?string $runId = null
    ): array {
        $pendingStoreIds = array_values($storeIds);
        $workers = [];
        $storeOutputs = [];
        $exitCodes = [];

        while ($pendingStoreIds && count($workers) < $maxProcesses) {
            $storeId = array_shift($pendingStoreIds);
            $workers[] = $this->startWorker($storeId, $runId);
            $storeOutputs[$storeId] = $this->createStoreOutput($storeId, $output, $storeLabels);
        }

        while ($workers) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            foreach ($workers as $index => $worker) {
                $storeOutput = $storeOutputs[$worker->getStoreId()];
                $this->forwardOutput($worker, $storeOutput);

                if ($worker->isRunning()) {
                    continue;
                }

                $this->writeChunk($worker->drainOutput(), $storeOutput);
                $storeOutput->flushTail();
                $exitCodes[$worker->getStoreId()] = $worker->getEffectiveExitCode();
                unset($workers[$index]);

                if ($pendingStoreIds) { //keep filling as workers free up
                    $storeId = array_shift($pendingStoreIds);
                    $workers[] = $this->startWorker($storeId, $runId);
                    $storeOutputs[$storeId] = $this->createStoreOutput($storeId, $output, $storeLabels);
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

    /**
     * One decorator per worker for the worker's whole lifespan: it holds the partial-line
     * buffer between polls.
     */
    protected function createStoreOutput(int $storeId, OutputInterface $output, array $storeLabels): StorePrefixedOutput
    {
        return new StorePrefixedOutput($output, $storeLabels[$storeId] ?? 'Store ' . $storeId);
    }

    protected function forwardOutput(WorkerProcess $worker, OutputInterface $output): void
    {
        $this->writeChunk($worker->getIncrementalOutput(), $output);
        $this->writeChunk($worker->getIncrementalErrorOutput(), $output);
    }

    protected function writeChunk(string $chunk, OutputInterface $output): void
    {
        if ($chunk !== '') {
            $output->write($chunk);
        }
    }
}
