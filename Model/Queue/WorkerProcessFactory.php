<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessFactory;

/**
 * Spawns a per-store queue worker as a child process and provides a structural recursion guard.
 * Children always receive a store ID (i.e. worker mode) and, optionally, a --run-id
 * correlation value, but never --max-processes or --sequential, so that a worker can never
 * reach the spawn logic again.
 */
class WorkerProcessFactory
{
    public function __construct(
        protected readonly ObjectManagerInterface $objectManager,
        protected readonly ProcessFactory $processFactory,
        protected readonly PhpExecutableFinder $phpFinder,
        protected readonly DirectoryList $directoryList
    ) {
    }

    public function create(int $storeId, ?string $runId = null): WorkerProcess
    {
        $process = $this->processFactory->create([
            'command' => $this->buildCommand($storeId, $runId),
            'cwd'     => $this->directoryList->getRoot(),
        ]);
        $process->setTimeout(null); // workers do not manage their lifespan, the orchestrator does

        return $this->objectManager->create(WorkerProcess::class, [
            'storeId' => $storeId,
            'process' => $process,
        ]);
    }

    protected function buildCommand(int $storeId, ?string $runId = null): array
    {
        $command = [$this->findPhpBinary(), 'bin/magento', 'algolia:queue:process', (string) $storeId];

        if ($runId !== null) {
            $command[] = '--run-id=' . $runId;
        }

        return $command;
    }

    protected function findPhpBinary(): string
    {
        return $this->phpFinder->find(false) ?: PHP_BINARY;
    }
}
