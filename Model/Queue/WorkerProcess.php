<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Symfony\Component\Process\Process;

/**
 * A spawned per-store queue worker. Wraps the underlying Symfony process so the pool
 * never touches Symfony directly - intended for per-store stdout structuring layering.
 */
class WorkerProcess
{
    public function __construct(
        protected readonly int $storeId,
        protected readonly Process $process,
    ) {
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function start(): void
    {
        $this->process->start();
    }

    public function isRunning(): bool
    {
        return $this->process->isRunning();
    }

    public function getExitCode(): ?int
    {
        return $this->process->getExitCode();
    }

    /**
     * A null exit code means the worker was killed or never finished on its own:
     * recorded as a failure rather than silently treated as success.
     */
    public function getEffectiveExitCode(): int
    {
        return $this->process->getExitCode() ?? 1;
    }

    /**
     * Reads and clears the underlying stdout buffer
     * @return string output produced since the previous read
     */
    public function getIncrementalOutput(): string
    {
        return $this->process->getOutput();
    }

    /**
     * Reads and clears the underlying stderr buffer
     * @return string error output since previous read
     */
    public function getIncrementalErrorOutput(): string
    {
        return $this->process->getErrorOutput();
    }

    /**
     * @return string Remaining buffered output of a finished worker.
     */
    public function drainOutput(): string
    {
        return $this->process->getOutput() . $this->process->getErrorOutput();
    }
}
