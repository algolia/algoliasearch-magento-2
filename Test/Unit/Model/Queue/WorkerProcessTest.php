<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Process\Process;

class WorkerProcessTest extends TestCase
{
    public function testStoreIdIsPairedWithTheUnderlyingProcess(): void
    {
        $process = $this->createStub(Process::class);

        $worker = new WorkerProcess(2, $process);

        $this->assertSame(2, $worker->getStoreId());
        $this->assertSame($process, $this->getPrivateProperty($worker, 'process'));
    }

    public function testStartIsForwardedToTheUnderlyingProcess(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->once())->method('start');

        $worker = new WorkerProcess(2, $process);
        $worker->start();
    }

    public function testLifecycleReadsAreForwardedToTheUnderlyingProcess(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('isRunning')->willReturn(true);
        $process->method('getExitCode')->willReturn(3);
        $process->method('getOutput')->willReturn('out');
        $process->method('getErrorOutput')->willReturn('err');

        $worker = new WorkerProcess(2, $process);

        $this->assertTrue($worker->isRunning());
        $this->assertSame(3, $worker->getExitCode());
        $this->assertSame('out', $worker->getIncrementalOutput());
        $this->assertSame('err', $worker->getIncrementalErrorOutput());
        $this->assertSame('outerr', $worker->drainOutput());
    }

    public function testEffectiveExitCodeKeepsARealExitCode(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(3);

        $worker = new WorkerProcess(2, $process);

        $this->assertSame(3, $worker->getEffectiveExitCode());
    }

    public function testEffectiveExitCodeMapsKilledWorkerToFailure(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(null);

        $worker = new WorkerProcess(2, $process);

        $this->assertNull($worker->getExitCode());
        $this->assertSame(1, $worker->getEffectiveExitCode());
    }
}
