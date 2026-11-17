<?php

namespace Algolia\AlgoliaSearch\Model\Queue;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\ObjectManagerInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\ProcessFactory;

/**
 * Spawns a per-store queue worker as a child process and provides a structural recursion guard.
 * Children always receive a store ID (i.e. worker mode) but never --max-processes or --sequential,
 * so that a worker can never reach the spawn logic again.
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

    public function create(int $storeId): WorkerProcess
    {
        $process = $this->processFactory->create([
            'command' => $this->buildCommand($storeId),
            'cwd'     => $this->directoryList->getRoot()
        ]);
        $process->setTimeout(null); // workers do not manage their lifespan, the orchestrator does
        return $this->objectManager->create(WorkerProcess::class, [
            'storeId' => $storeId,
            'process' => $process,
        ]);
    }

    protected function buildCommand(int $storeId): array
    {
        return [$this->findPhpBinary(), 'bin/magento', 'algolia:queue:process', (string) $storeId];
    }

    protected function findPhpBinary(): string
    {
        return $this->phpFinder->find(false) ?: PHP_BINARY;
    }
}
