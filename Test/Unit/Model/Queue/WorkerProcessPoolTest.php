<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessFactory;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

class WorkerProcessPoolTest extends TestCase
{
    /**
     * Factory stub mapping each store ID to a WorkerProcess over a scripted Process: the
     * worker reports running for the given number of pool polls, then exits with the given
     * code, shifting one output/error chunk off per read call. Creation and exit events are
     * appended to $timeline as create:<id> / exit:<id> so tests can reason about concurrency
     * and refill order.
     *
     * @param array<int, array{0: int, 1: int|null, 2: list<string>, 3: list<string>}> $lifecyclesByStore
     * @param string[] $timeline
     */
    private function createScriptedFactory(array $lifecyclesByStore, array &$timeline = []): WorkerProcessFactory
    {
        $factory = $this->createStub(WorkerProcessFactory::class);
        $factory->method('create')->willReturnCallback(
            function (int $storeId) use ($lifecyclesByStore, &$timeline) {
                [$pollsBeforeExit, $exitCode, $outputChunks, $errorChunks] = $lifecyclesByStore[$storeId];
                $timeline[] = 'create:' . $storeId;

                $isRunningCalls = 0;
                $exitRecorded = false;
                $process = $this->createStub(Process::class);
                $process->method('start');
                $process->method('isRunning')->willReturnCallback(
                    function () use (&$isRunningCalls, $pollsBeforeExit) {
                        return $isRunningCalls++ < $pollsBeforeExit;
                    }
                );
                $process->method('getExitCode')->willReturnCallback(
                    function () use (&$exitRecorded, &$timeline, $storeId, $exitCode) {
                        if (!$exitRecorded) {
                            $exitRecorded = true;
                            $timeline[] = 'exit:' . $storeId;
                        }

                        return $exitCode;
                    }
                );
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

                return new WorkerProcess($storeId, $process);
            }
        );

        return $factory;
    }

    /**
     * The real pool sleeps 100ms per poll; polling is scripted here, so the wait is dropped.
     */
    private function createPool(WorkerProcessFactory $factory): WorkerProcessPool
    {
        return new class ($factory) extends WorkerProcessPool {
            protected const int POLL_INTERVAL_MICROSECONDS = 0;
        };
    }

    public function testRunStartsUpToMaxProcessesWorkersAndRefillsAsTheyExit(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([
            2 => [1, 0, [], []],
            3 => [3, 0, [], []],
            4 => [1, 0, [], []],
        ], $timeline);

        $exitCodes = $this->createPool($factory)->run([2, 3, 4], 2, new BufferedOutput());

        // Store 4 is only spawned after store 2 exited (refill), not alongside 2 and 3
        $this->assertSame(
            ['create:2', 'create:3', 'exit:2', 'create:4', 'exit:3', 'exit:4'],
            $timeline
        );
        $this->assertSame([2 => 0, 3 => 0, 4 => 0], $exitCodes);
    }

    public function testRunReturnsExitCodesKeyedByStoreId(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, [], []],
            3 => [2, 1, [], []],
            4 => [1, 7, [], []],
        ]);

        $exitCodes = $this->createPool($factory)->run([2, 3, 4], 2, new BufferedOutput());

        $this->assertSame([2 => 0, 3 => 1, 4 => 7], $exitCodes);
    }

    public function testRunRecordsKilledWorkerAsFailure(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, null, [], []],
            3 => [2, 0, [], []],
        ]);

        $exitCodes = $this->createPool($factory)->run([2, 3], 2, new BufferedOutput());

        $this->assertSame([2 => 1, 3 => 0], $exitCodes);
    }

    public function testRunForwardsWorkerOutputToTheOutputInterface(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ['a', 'b'], ['e1']],
            3 => [2, 0, [], []],
        ]);
        $output = new BufferedOutput();

        $this->createPool($factory)->run([2, 3], 2, $output);

        $written = $output->fetch();
        $this->assertStringContainsString('a', $written);
        $this->assertStringContainsString('b', $written);
        $this->assertStringContainsString('e1', $written);
    }

    public function testRunCompletesEveryStoreExactlyOnceDespiteAFailingWorker(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([
            2 => [1, 1, [], []],
            3 => [1, 0, [], []],
            4 => [1, 0, [], []],
        ], $timeline);

        $exitCodes = $this->createPool($factory)->run([2, 3, 4], 3, new BufferedOutput());

        $this->assertSame(['create:2', 'create:3', 'create:4', 'exit:2', 'exit:3', 'exit:4'], $timeline);
        $this->assertSame([2 => 1, 3 => 0, 4 => 0], $exitCodes);
    }

    public function testRunReturnsNoExitCodesWhenThereAreNoStores(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([], $timeline);

        $exitCodes = $this->createPool($factory)->run([], 4, new BufferedOutput());

        $this->assertSame([], $exitCodes);
        $this->assertSame([], $timeline);
    }
}
