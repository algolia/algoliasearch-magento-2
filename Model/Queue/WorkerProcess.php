<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBuffer;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBufferFactory;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Symfony\Component\Process\Process;

/**
 * A spawned per-store queue worker. Wraps the underlying Symfony process so the pool never
 * touches Symfony directly, and owns the worker's output structuring: stdout and stderr
 * are kept in separate line buffers and surfaced as complete lines prefixed with the
 * store label.
 */
class WorkerProcess
{
    protected WorkerLineBuffer $stdoutBuffer;

    protected WorkerLineBuffer $stderrBuffer;

    protected ?string $label = null;

    public function __construct(
        protected readonly int                     $storeId,
        protected readonly Process                 $process,
        protected readonly WorkerLineBufferFactory $workerLineBufferFactory,
        protected readonly StoreNameFetcher        $storeNameFetcher,
    ) {
        $this->stdoutBuffer = $this->workerLineBufferFactory->create();
        $this->stderrBuffer = $this->workerLineBufferFactory->create();
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
     * Complete lines produced since the previous call, each prefixed with the store
     * label; stdout lines come before stderr lines, and ordering across the two streams
     * is only guaranteed at poll granularity. Once the worker has terminated, the
     * remaining partial lines are flushed as well, so later calls return nothing.
     *
     * @return string[]
     */
    public function readLines(): array
    {
        $lines = $this->prefixLines([
            ...$this->stdoutBuffer->push($this->process->getIncrementalOutput()),
            ...$this->stderrBuffer->push($this->process->getIncrementalErrorOutput()),
        ]);

        if (!$this->process->isRunning()) {
            foreach ([$this->stdoutBuffer, $this->stderrBuffer] as $buffer) {
                // Pick up any unfinished incremental output before flushing the tail
                $lines = [
                    ...$lines,
                    ...$this->prefixLines([
                        ...$this->stdoutBuffer->push($this->process->getIncrementalOutput()),
                        ...$this->stderrBuffer->push($this->process->getIncrementalErrorOutput()),
                    ]),
                ];

                $tail = $buffer->flush();

                if ($tail !== null) {
                    $lines[] = $this->prefixLine($tail);
                }
            }
        }

        return $lines;
    }

    /**
     * @param string[] $lines
     *
     * @return string[]
     */
    protected function prefixLines(array $lines): array
    {
        return array_map(fn (string $line) => $this->prefixLine($line), $lines);
    }

    protected function prefixLine(string $line): string
    {
        return '[' . $this->getLabel() . '] ' . $line;
    }

    protected function getLabel(): string
    {
        return $this->label ??= $this->storeNameFetcher->getStoreLabel($this->storeId);
    }
}
