<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Spawns a per-store queue worker as a child process and provides a structural recursion guard.
 * Children always receive a store ID (i.e. worker mode) but never --max-processes or --sequential,
 * so that a worker can never reach the spawn logic again.
 */
class WorkerProcessFactory
{
    public function create(int $storeId): WorkerProcess
    {
        return new WorkerProcess(
            $storeId,
            new Process($this->buildCommand($storeId), BP, null, null, null)
        );
    }

    protected function buildCommand(int $storeId): array
    {
        return [$this->findPhpBinary(), 'bin/magento', 'algolia:queue:process', (string) $storeId];
    }

    protected function findPhpBinary(): string
    {
        return (new PhpExecutableFinder())->find(false) ?: PHP_BINARY;
    }
}
