<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBuffer;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBufferFactory;

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
        protected WorkerLineBufferFactory $workerLineBufferFactory,
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
        QueueReporterInterface $reporter,
        array $storeLabels = [],
        ?string $runId = null
    ): array {
        $pendingStoreIds = array_values($storeIds);
        $workers = [];
        $storeBuffers = [];
        $exitCodes = [];

        while ($pendingStoreIds && count($workers) < $maxProcesses) {
            $storeId = array_shift($pendingStoreIds);
            $workers[] = $this->startWorker($storeId, $runId);
            $storeBuffers[$storeId] = $this->createStoreBuffer();
        }

        while ($workers) {
            usleep(self::POLL_INTERVAL_MICROSECONDS);

            /**
             * @var WorkerProcess $worker
             */
            foreach ($workers as $index => $worker) {
                $buffer = $storeBuffers[$worker->getStoreId()];
                $label = $storeLabels[$worker->getStoreId()] ?? 'Store ' . $worker->getStoreId();

                $this->forwardOutput($worker, $buffer, $reporter, $label);

                if ($worker->isRunning()) {
                    continue;
                }

                $this->writeChunk($worker->drainOutput(), $buffer, $reporter, $label);
                $this->flushBuffer($buffer, $reporter, $label);
                $exitCodes[$worker->getStoreId()] = $worker->getEffectiveExitCode();
                unset($workers[$index]);

                if ($pendingStoreIds) { //keep filling as workers free up
                    $storeId = array_shift($pendingStoreIds);
                    $workers[] = $this->startWorker($storeId, $runId);
                    $storeBuffers[$storeId] = $this->createStoreBuffer();
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
     * One buffer per worker for the worker's whole lifespan: it holds the partial-line
     * buffer between polls.
     */
    protected function createStoreBuffer(): WorkerLineBuffer
    {
        return $this->workerLineBufferFactory->create();
    }

    protected function forwardOutput(
        WorkerProcess $worker,
        WorkerLineBuffer $buffer,
        QueueReporterInterface $reporter,
        string $label
    ): void {
        $this->writeChunk($worker->getIncrementalOutput(), $buffer, $reporter, $label);
        $this->writeChunk($worker->getIncrementalErrorOutput(), $buffer, $reporter, $label);
    }

    protected function writeChunk(string $chunk, WorkerLineBuffer $buffer, QueueReporterInterface $reporter, string $label): void
    {
        foreach ($buffer->push($chunk) as $line) {
            $reporter->log($this->prefixLine($label, $line));
        }
    }

    protected function flushBuffer(WorkerLineBuffer $buffer, QueueReporterInterface $reporter, string $label): void
    {
        $tail = $buffer->flush();

        if ($tail !== null) {
            $reporter->log($this->prefixLine($label, $tail));
        }
    }

    protected function prefixLine(string $label, string $line): string
    {
        return '[' . $label . '] ' . $line;
    }
}
