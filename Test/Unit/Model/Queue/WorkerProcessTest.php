<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBuffer;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBufferFactory;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Process\Process;

class WorkerProcessTest extends TestCase
{
    private function createObjectToTest(
        int $storeId,
        Process $process,
        ?StoreNameFetcher $storeNameFetcher = null
    ): WorkerProcess {
        $storeNameFetcher ??= $this->createStoreNameFetcher('Store ' . $storeId . ' (English)');

        return new WorkerProcess($storeId, $process, $this->createWorkerLineBufferFactory(), $storeNameFetcher);
    }

    /**
     * Mirrors the Magento-generated factory: each create() returns a fresh instance,
     * so every worker stream gets its own buffer state
     */
    private function createWorkerLineBufferFactory(): WorkerLineBufferFactory
    {
        $workerLineBufferFactory = $this->createStub(WorkerLineBufferFactory::class);
        $workerLineBufferFactory->method('create')->willReturnCallback(fn () => new WorkerLineBuffer());

        return $workerLineBufferFactory;
    }

    private function createStoreNameFetcher(string $label): StoreNameFetcher
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreLabel')->willReturn($label);

        return $storeNameFetcher;
    }

    /**
     * @param string[] $outputChunks
     * @param string[] $errorChunks
     */
    private function createScriptedProcess(bool $running, array $outputChunks = [], array $errorChunks = []): Process
    {
        $process = $this->createStub(Process::class);
        $process->method('isRunning')->willReturn($running);
        $process->method('getOutput')->willReturnCallback(
            function () use (&$outputChunks) {
                return array_shift($outputChunks) ?? '';
            }
        );
        $process->method('getErrorOutput')->willReturnCallback(
            function () use (&$errorChunks) {
                return array_shift($errorChunks) ?? '';
            }
        );

        return $process;
    }

    public function testStoreIdIsPairedWithTheUnderlyingProcess(): void
    {
        $process = $this->createStub(Process::class);

        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame(2, $worker->getStoreId());
        $this->assertSame($process, $this->getPrivateProperty($worker, 'process'));
    }

    public function testStartIsForwardedToTheUnderlyingProcess(): void
    {
        $process = $this->createMock(Process::class);
        $process->expects($this->once())->method('start');

        $this->createObjectToTest(2, $process)->start();
    }

    public function testReadLinesReturnsPrefixedCompleteLinesFromBothStreams(): void
    {
        $process = $this->createScriptedProcess(true, ["line one\nline two\n"], ["error\n"]);

        $this->assertSame(
            [
                '[Store 2 (English)] line one',
                '[Store 2 (English)] line two',
                '[Store 2 (English)] error',
            ],
            $this->createObjectToTest(2, $process)->readLines()
        );
    }

    public function testReadLinesBuffersAPartialStdoutLineAcrossPolls(): void
    {
        $process = $this->createScriptedProcess(true, ['par', "tial\n"]);
        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame([], $worker->readLines());
        $this->assertSame(['[Store 2 (English)] partial'], $worker->readLines());
    }

    public function testReadLinesKeepsStdoutAndStderrSeparateAcrossPolls(): void
    {
        // A stderr chunk arriving while stdout holds a partial line must not be glued
        // to it: each stream's lines stay intact
        $process = $this->createScriptedProcess(true, ['partial ', "out done\n"], ["err line\n"]);
        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame(['[Store 2 (English)] err line'], $worker->readLines());
        $this->assertSame(['[Store 2 (English)] partial out done'], $worker->readLines());
    }

    public function testReadLinesFlushesBothTailsOnceTerminated(): void
    {
        $process = $this->createScriptedProcess(false, ["complete\npartial out"], ['partial err']);
        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame(
            [
                '[Store 2 (English)] complete',
                '[Store 2 (English)] partial out',
                '[Store 2 (English)] partial err',
            ],
            $worker->readLines()
        );

        // Flushing is idempotent: later calls return nothing
        $this->assertSame([], $worker->readLines());
    }

    public function testReadLinesFlushesOnlyTheRemainingTailAfterCompleteLines(): void
    {
        $process = $this->createScriptedProcess(false, ["complete line\nfinal partial"]);
        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame(
            [
                '[Store 2 (English)] complete line',
                '[Store 2 (English)] final partial',
            ],
            $worker->readLines()
        );
    }

    public function testReadLinesUsesTheLabelResolvedByTheStoreNameFetcher(): void
    {
        $process = $this->createScriptedProcess(true, ["store line\n"]);
        $worker = $this->createObjectToTest(5, $process, $this->createStoreNameFetcher('Store 5 (French)'));

        $this->assertSame(['[Store 5 (French)] store line'], $worker->readLines());
    }

    public function testReadLinesMemoizesTheLabel(): void
    {
        $process = $this->createScriptedProcess(true, ["one\n", "two\n"], []);
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->once())->method('getStoreLabel')->willReturn('Store 2 (English)');

        $worker = new WorkerProcess(2, $process, $this->createWorkerLineBufferFactory(), $storeNameFetcher);
        $worker->readLines();
        $worker->readLines();
    }

    public function testEffectiveExitCodeKeepsARealExitCode(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(3);

        $worker = $this->createObjectToTest(2, $process);

        $this->assertSame(3, $worker->getEffectiveExitCode());
    }

    public function testEffectiveExitCodeMapsKilledWorkerToFailure(): void
    {
        $process = $this->createStub(Process::class);
        $process->method('getExitCode')->willReturn(null);

        $worker = $this->createObjectToTest(2, $process);

        $this->assertNull($worker->getExitCode());
        $this->assertSame(1, $worker->getEffectiveExitCode());
    }
}
