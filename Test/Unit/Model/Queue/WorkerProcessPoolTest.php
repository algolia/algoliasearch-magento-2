<?php

namespace Algolia\AlgoliaSearch\Test\Unit\Model\Queue;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBuffer;
use Algolia\AlgoliaSearch\Model\Queue\Output\WorkerLineBufferFactory;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessFactory;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Process\Process;

class WorkerProcessPoolTest extends TestCase
{
    /**
     * Factory stub mapping each store ID to a WorkerProcess over a scripted Process, with
     * the following per-store lifecycle definition ($lifecyclesByStore array listed by position):
     *
     *  - 0: pollsBeforeExit - the number of pool polls for which the worker reports
     *    isRunning() = true; it exits on the poll after that
     *  - 1: exitCode - the value getExitCode() returns once exited; null simulates a
     *    killed worker, which the pool records as a failure (effective exit code 1)
     *  - 2: outputChunks - stdout chunks, shifted one per readLines() call (empty string
     *    once exhausted)
     *  - 3: errorChunks - stderr chunks, same shifting behavior as outputChunks
     *
     * Each created worker gets its own label fetcher (from $labelsByStore, or a default
     * label format) and fresh line buffers, mirroring the Magento-generated factory.
     *
     * Creation and exit events are appended to $timeline as create:<id> / exit:<id> so
     * tests can reason about concurrency and refill order.
     *
     * @param array<int, array{0: int, 1: int|null, 2: list<string>, 3: list<string>}> $lifecyclesByStore
     * @param array<int, string> $labelsByStore
     * @param string[] $timeline
     */
    private function createScriptedFactory(
        array $lifecyclesByStore,
        array $labelsByStore = [],
        array &$timeline = []
    ): WorkerProcessFactory {
        $factory = $this->createStub(WorkerProcessFactory::class);
        $factory->method('create')->willReturnCallback(
            function (int $storeId, ?string $runId = null) use ($lifecyclesByStore, $labelsByStore, &$timeline) {
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

                $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
                $storeNameFetcher->method('getStoreLabel')->willReturn(
                    $labelsByStore[$storeId] ?? 'Store ' . $storeId
                );

                $workerLineBufferFactory = $this->createStub(WorkerLineBufferFactory::class);
                $workerLineBufferFactory->method('create')->willReturnCallback(fn () => new WorkerLineBuffer());

                return new WorkerProcess($storeId, $process, $workerLineBufferFactory, $storeNameFetcher);
            }
        );

        return $factory;
    }

    /**
     * The real pool sleeps 100ms per poll; Test object drops the wait
     */
    private function createObjectToTest(WorkerProcessFactory $factory): WorkerProcessPool
    {
        return new class ($factory) extends WorkerProcessPool {
            protected const int POLL_INTERVAL_MICROSECONDS = 0;
        };
    }

    private function createReporter(BufferedOutput $output): ConsoleQueueReporter
    {
        return new ConsoleQueueReporter($output);
    }

    public function testRunStartsUpToMaxProcessesWorkersAndRefillsAsTheyExit(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([
            2 => [1, 0, [], []],
            3 => [3, 0, [], []],
            4 => [1, 0, [], []],
        ], [], $timeline);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3, 4],
            2,
            $this->createStub(ConsoleQueueReporter::class)
        );

        // Store 4 is only spawned after store 2 exited (refill), not alongside 2 and 3
        $this->assertSame(
            ['create:2', 'create:3', 'exit:2', 'create:4', 'exit:3', 'exit:4'],
            $timeline
        );
        $this->assertSame([2 => 0, 3 => 0, 4 => 0], $exitCodes);
    }

    public function testRunNeverCreatesMoreWorkersThanPendingStores(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([
            2 => [1, 0, [], []],
            3 => [1, 0, [], []],
        ], [], $timeline);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3],
            8,
            $this->createStub(ConsoleQueueReporter::class)
        );

        // The concurrency cap (8) far exceeds the pending store count: only one worker
        // per store is ever created, and no refill happens once both are running
        $this->assertSame(['create:2', 'create:3', 'exit:2', 'exit:3'], $timeline);
        $this->assertSame([2 => 0, 3 => 0], $exitCodes);
    }

    public function testRunReturnsExitCodesKeyedByStoreId(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, [], []],
            3 => [2, 1, [], []],
            4 => [1, 7, [], []],
        ]);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3, 4],
            2,
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertSame([2 => 0, 3 => 1, 4 => 7], $exitCodes);
    }

    public function testRunRecordsKilledWorkerAsFailure(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, null, [], []],
            3 => [2, 0, [], []],
        ]);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3],
            2,
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertSame([2 => 1, 3 => 0], $exitCodes);
    }

    public function testRunForwardsWorkerOutputToTheReporter(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ['a', 'b'], ['e1']],
            3 => [2, 0, [], []],
        ]);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2, 3], 2, $this->createReporter($output));

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
        ], [], $timeline);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3, 4],
            3,
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertSame(['create:2', 'create:3', 'create:4', 'exit:2', 'exit:3', 'exit:4'], $timeline);
        $this->assertSame([2 => 1, 3 => 0, 4 => 0], $exitCodes);
    }

    public function testRunReturnsNoExitCodesWhenThereAreNoStores(): void
    {
        $timeline = [];
        $factory = $this->createScriptedFactory([], [], $timeline);

        $exitCodes = $this->createObjectToTest($factory)->run(
            [],
            4,
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertSame([], $exitCodes);
        $this->assertSame([], $timeline);
    }

    public function testRunForwardsRunIdToTheFactoryForEverySpawnedWorker(): void
    {
        $createArgs = [];
        $factory = $this->createStub(WorkerProcessFactory::class);
        $factory->method('create')->willReturnCallback(
            function (int $storeId, ?string $runId = null) use (&$createArgs) {
                $createArgs[] = [$storeId, $runId];

                $process = $this->createStub(Process::class);
                $process->method('start');
                $process->method('isRunning')->willReturn(false);
                $process->method('getExitCode')->willReturn(0);

                $storeNameFetcher = $this->createStub(StoreNameFetcher::class);

                $workerLineBufferFactory = $this->createStub(WorkerLineBufferFactory::class);
                $workerLineBufferFactory->method('create')->willReturnCallback(fn () => new WorkerLineBuffer());

                return new WorkerProcess($storeId, $process, $workerLineBufferFactory, $storeNameFetcher);
            }
        );

        $exitCodes = $this->createObjectToTest($factory)->run(
            [2, 3],
            2,
            $this->createStub(ConsoleQueueReporter::class),
            'run-abc123'
        );

        $this->assertSame([[2, 'run-abc123'], [3, 'run-abc123']], $createArgs);
        $this->assertSame([2 => 0, 3 => 0], $exitCodes);
    }

    public function testRunPrefixesWorkerLinesWithTheStoreLabel(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ["line one\nline two\n"], ["error\n"]],
        ], [2 => 'Store 2 (English)']);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2], 1, $this->createReporter($output));

        $written = $output->fetch();
        $this->assertStringContainsString('[Store 2 (English)] line one', $written);
        $this->assertStringContainsString('[Store 2 (English)] line two', $written);
        $this->assertStringContainsString('[Store 2 (English)] error', $written);
    }

    public function testRunPrefixesEachStoreWithItsOwnLabel(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [2, 0, ["from store 2\n"], []],
            3 => [1, 0, ["from store 3\n"], []],
        ], [2 => 'Store 2 (Second)', 3 => 'Store 3 (Third)']);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2, 3], 2, $this->createReporter($output));

        $written = $output->fetch();
        $this->assertStringContainsString('[Store 2 (Second)] from store 2', $written);
        $this->assertStringContainsString('[Store 3 (Third)] from store 3', $written);
        $this->assertStringNotContainsString('[Store 2 (Second)] from store 3', $written);
        $this->assertStringNotContainsString('[Store 3 (Third)] from store 2', $written);
    }

    public function testRunFlushesThePartialTailWhenAWorkerExitsWithoutATrailingNewline(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ['a line with no newline'], []],
        ], [2 => 'Store 2 (English)']);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2], 1, $this->createReporter($output));

        $this->assertStringContainsString(
            '[Store 2 (English)] a line with no newline',
            $output->fetch()
        );
    }

    public function testRunKeepsThePartialLineBufferOfAWorkerAcrossPolls(): void
    {
        // The partial line spans two polls; poll 1 buffers it, poll 2 completes it
        $factory = $this->createScriptedFactory([
            2 => [2, 0, ['par', "tial\n"], []],
        ], [2 => 'Store 2 (English)']);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2], 1, $this->createReporter($output));

        $this->assertStringContainsString('[Store 2 (English)] partial', $output->fetch());
    }

    public function testRunEmitsTheFinalPartialLineBeforeReaping(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ["complete line\nfinal partial"], []],
        ], [2 => 'Store 2 (English)']);
        $output = new BufferedOutput();

        $this->createObjectToTest($factory)->run([2], 1, $this->createReporter($output));

        $written = $output->fetch();
        $this->assertStringContainsString('[Store 2 (English)] complete line', $written);
        $this->assertStringContainsString('[Store 2 (English)] final partial', $written);
        // The tail must be a prefixed line of its own, not glued to the drained chunk
        $this->assertStringNotContainsString('final partial[Store 2', $written);
    }

    public function testRunKeepsStdoutAndStderrLinesSeparateAcrossPolls(): void
    {
        // Regression: a stderr chunk arriving while stdout holds a partial line used to
        // be appended to the same buffer, gluing the two streams into one corrupt line
        $factory = $this->createScriptedFactory([
            2 => [2, 0, ['partial ', "out done\n"], ["err line\n"]],
        ], [2 => 'Store 2 (English)']);

        $logs = [];
        $reporter = $this->createStub(QueueReporterInterface::class);
        $reporter->method('log')->willReturnCallback(
            function (string $message) use (&$logs) {
                $logs[] = $message;
            }
        );

        $this->createObjectToTest($factory)->run([2], 1, $reporter);

        $this->assertSame(
            [
                '[Store 2 (English)] err line',
                '[Store 2 (English)] partial out done',
            ],
            $logs
        );
    }

    public function testRunRelaysExactlyPrefixedLinesThroughTheReporter(): void
    {
        $factory = $this->createScriptedFactory([
            2 => [1, 0, ["line one\n"], ["error line\n"]],
        ], [2 => 'Store 2 (English)']);

        $logs = [];
        $reporter = $this->createStub(QueueReporterInterface::class);
        $reporter->method('log')->willReturnCallback(
            function (string $message) use (&$logs) {
                $logs[] = $message;
            }
        );

        $this->createObjectToTest($factory)->run([2], 1, $reporter);

        // Both stdout and stderr lines are relayed through log(), prefixed per store
        $this->assertSame(
            [
                '[Store 2 (English)] line one',
                '[Store 2 (English)] error line',
            ],
            $logs
        );
    }
}
