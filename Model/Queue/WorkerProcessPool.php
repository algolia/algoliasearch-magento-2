<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Runs one worker per store ID, up to a concurrency cap, refilling the pool as workers
 * exit. Symfony Process has no "wait for any" API, so the pool polls and refills;
 * Owns spawning, streaming and exit code collection only - no locks, no discovery,
 * no mode decisions, etc.
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
     *
     * @return array<int, int> Exit codes keyed by store ID (killed workers count as failures)
     */
    public function run(array $storeIds, int $maxProcesses, OutputInterface $output): array
    {
        $pendingStoreIds = array_values($storeIds);
        $workers = [];
        $exitCodes = [];

        while ($pendingStoreIds && count($workers) < $maxProcesses) {
            $workers[] = $this->startWorker(array_shift($pendingStoreIds));
        }

        while ($workers) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            foreach ($workers as $index => $worker) {
                $this->forwardOutput($worker, $output);

                if ($worker->isRunning()) {
                    continue;
                }

                $this->writeChunk($worker->drainOutput(), $output);
                $exitCodes[$worker->getStoreId()] = $worker->getEffectiveExitCode();
                unset($workers[$index]);

                if ($pendingStoreIds) { //keep filling as workers free up
                    $workers[] = $this->startWorker(array_shift($pendingStoreIds));
                }
            }
        }

        return $exitCodes;
    }

    protected function startWorker(int $storeId): WorkerProcess
    {
        $worker = $this->workerProcessFactory->create($storeId);
        $worker->start();

        return $worker;
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
