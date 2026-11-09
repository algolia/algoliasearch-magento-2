<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessFactory;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Process\Process;

class WorkerProcessFactoryTest extends TestCase
{
    public function testCreateReturnsWorkerCarryingTheStoreId(): void
    {
        $worker = (new WorkerProcessFactory())->create(2);

        $this->assertInstanceOf(WorkerProcess::class, $worker);
        $this->assertSame(2, $worker->getStoreId());
    }

    public function testCreateBuildsAWorkerProcessForTheGivenStore(): void
    {
        $process = $this->getPrivateProperty((new WorkerProcessFactory())->create(2), 'process');

        $this->assertInstanceOf(Process::class, $process);
        $this->assertSame(BP, $process->getWorkingDirectory());
        $this->assertNull($process->getTimeout());
        $commandLine = $process->getCommandLine();
        $this->assertStringContainsString('bin/magento', $commandLine);
        $this->assertStringContainsString('algolia:queue:process', $commandLine);
        $this->assertStringContainsString("'2'", $commandLine);
    }

    public function testBuildCommandSeamProducesPhpBinaryMagentoAndStoreId(): void
    {
        $command = $this->invokeMethod(new WorkerProcessFactory(), 'buildCommand', [2]);

        $this->assertCount(4, $command);
        $this->assertNotEmpty($command[0]);
        $this->assertSame('bin/magento', $command[1]);
        $this->assertSame('algolia:queue:process', $command[2]);
        $this->assertSame('2', $command[3]);
    }

    public function testCreateUsesTheBuildCommandSeam(): void
    {
        $factory = new class extends WorkerProcessFactory {
            protected function buildCommand(int $storeId): array
            {
                return ['echo', 'store-' . $storeId];
            }
        };

        $process = $this->getPrivateProperty($factory->create(5), 'process');

        $this->assertStringContainsString('store-5', $process->getCommandLine());
    }

    public function testFindPhpBinaryReturnsANonEmptyExecutablePath(): void
    {
        $this->assertNotEmpty($this->invokeMethod(new WorkerProcessFactory(), 'findPhpBinary'));
    }
}
