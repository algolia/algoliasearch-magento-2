<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Queue;

use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Queue\QueueOrchestrator;
use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Lock\LockManagerInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueOrchestratorTest extends TestCase
{
    private const string RUN_ID_PATTERN = '/^[0-9a-f]{32}$/';

    protected function createObjectToTest(
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?LockManagerInterface $lockManager = null,
        ?StoreNameFetcher $storeNameFetcher = null,
        ?MaxProcessesResolver $maxProcessesResolver = null,
        ?WorkerProcessPool $workerProcessPool = null,
    ): QueueOrchestrator {
        $credentialsManager ??= $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        return new QueueOrchestrator(
            $queue ?? $this->createStub(Queue::class),
            $lockManager ?? $this->createStub(LockManagerInterface::class),
            $maxProcessesResolver ?? $this->createStub(MaxProcessesResolver::class),
            $workerProcessPool ?? $this->createStub(WorkerProcessPool::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $credentialsManager,
        );
    }

    protected function createReporter(BufferedOutput $output): ConsoleQueueReporter
    {
        return new ConsoleQueueReporter($output);
    }

    /**
     * @param array<int, string> $labelsByStore
     */
    protected function createStoreNameFetcher(array $labelsByStore = []): StoreNameFetcher
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreLabel')->willReturnCallback(
            fn (int $storeId) => $labelsByStore[$storeId] ?? 'Store ' . $storeId
        );

        return $storeNameFetcher;
    }

    public function testRunSequentialRunsCronAndSucceeds(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $credentialsManager->expects($this->never())->method('getErrorMessage');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with();

        $success = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager)
            ->runSequential($this->createStub(ConsoleQueueReporter::class));

        $this->assertTrue($success);
    }

    public function testRunSequentialReportsMissingCredentialsAndDoesNotRun(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        // Funneled through the reporter: displayErrorMessage() would write to the manager's own output
        $credentialsManager->expects($this->never())->method('displayErrorMessage');
        $credentialsManager->expects($this->once())
            ->method('getErrorMessage')
            ->with(QueueOrchestrator::class)
            ->willReturn('credentials message from the manager');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $reporter = $this->createMock(ConsoleQueueReporter::class);
        $reporter->expects($this->once())->method('error')->with('credentials message from the manager');

        $success = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager)
            ->runSequential($reporter);

        $this->assertFalse($success);
    }

    public function testRunSequentialReportsErrorOnDatabaseException(): void
    {
        $queue = $this->createStub(Queue::class);
        $queue->method('runCron')->willThrowException(new \Zend_Db_Exception('database unavailable'));

        $reporter = $this->createMock(ConsoleQueueReporter::class);
        $reporter->expects($this->once())->method('error')->with('database unavailable');

        $success = $this->createObjectToTest(queue: $queue)->runSequential($reporter);

        $this->assertFalse($success);
    }

    public function testOrchestrateSkipsWhenLockIsHeld(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(QueueOrchestrator::ORCHESTRATOR_LOCK_NAME, 0)
            ->willReturn(false);
        // Never acquired, so never unlocked
        $lockManager->expects($this->never())->method('unlock');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('discoverPendingStoreIds');
        $queue->expects($this->never())->method('runCron');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $this->assertTrue($success);
        $this->assertStringContainsString('already running', $output->fetch());
    }

    public function testOrchestrateFallsBackToSequentialWhenStorelessJobsArePending(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2, 3]);
        $queue->method('hasStorelessPendingJobs')->willReturn(true);
        $queue->expects($this->once())->method('runCron')->with();

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $this->assertTrue($success);
        $this->assertStringContainsString('Store-agnostic indexing jobs are pending', $output->fetch());
    }

    public function testOrchestrateStorelessFallbackReportsMissingCredentialsAndFails(): void
    {
        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->method('getErrorMessage')->willReturn('credentials message from the manager');

        $queue = $this->createMock(Queue::class);
        $queue->method('hasStorelessPendingJobs')->willReturn(true);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $reporter = $this->createMock(ConsoleQueueReporter::class);
        $reporter->expects($this->once())->method('error')->with('credentials message from the manager');

        $success = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager, lockManager: $lockManager)
            ->orchestrate(null, $reporter);

        $this->assertFalse($success);
    }

    public function testOrchestrateReportsNoPendingJobsAndSucceeds(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->never())->method('getStoreLabel');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(QueueOrchestrator::ORCHESTRATOR_LOCK_NAME);

        $queue = $this->createMock(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([]);
        $queue->method('hasStorelessPendingJobs')->willReturn(false);
        $queue->expects($this->never())->method('runCron');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $this->assertTrue($success);
        $this->assertStringContainsString('No pending indexing queue jobs.', $output->fetch());
    }

    public function testOrchestrateRunsPoolWithDiscoveredStoresAndClampedCap(): void
    {
        $storeIds = [2, 3, 4, 5, 6];
        $storeNames = ['Second Store', 'Third Store', 'Fourth Store', 'Fifth Store', 'Sixth Store'];

        $storeLabels = [];
        foreach ($storeIds as $i => $storeId) {
            $storeLabels[$storeId] = sprintf('Store %d (%s)', $storeId, $storeNames[$i]);
        }
        $storeNameFetcher = $this->createStoreNameFetcher($storeLabels);

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        // The orchestrator gates per store in the children: no global credentials check here
        $credentialsManager->expects($this->never())->method('checkCredentialsWithSearchOnlyAPIKey');

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn($storeIds);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturnCallback(
            static fn (?int $cliOverride, ?int $pendingStores) => $pendingStores === null ? 4 : 2
        );

        $output = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with(
                $storeIds,
                2,
                $this->isInstanceOf(QueueReporterInterface::class),
                $this->matchesRegularExpression(self::RUN_ID_PATTERN)
            )
            ->willReturn(array_fill_keys($storeIds, 0));

        $success = $this->createObjectToTest(
            queue: $queue,
            credentialsManager: $credentialsManager,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $fetch = $output->fetch();
        $this->assertTrue($success);
        $this->assertStringContainsString('with up to 2 worker(s)', $fetch);
        foreach ($storeIds as $i => $storeId) {
            $this->assertStringContainsString(
                sprintf('Store %d (%s): completed', $storeId, $storeNames[$i]),
                $fetch
            );
        }
    }

    public function testOrchestratePassesOverrideToResolver(): void
    {
        $storeNameFetcher = $this->createStoreNameFetcher([2 => 'Store 2 (Second Store)']);

        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())
            ->method('resolve')
            ->with(7, 1)
            ->willReturn(1);

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2]);

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with(
                [2],
                1,
                $this->isInstanceOf(QueueReporterInterface::class),
                $this->matchesRegularExpression(self::RUN_ID_PATTERN)
            )
            ->willReturn([2 => 0]);

        $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(7, $this->createStub(ConsoleQueueReporter::class));
    }

    public function testOrchestrateSpawnsSingleWorkerForSinglePendingStore(): void
    {
        $storeNameFetcher = $this->createStoreNameFetcher([5 => 'Store 5 (Fifth Store)']);

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([5]);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturnCallback(
            static fn (?int $cliOverride, ?int $pendingStores) => $pendingStores === null ? 4 : 1
        );

        $output = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with(
                [5],
                1,
                $this->isInstanceOf(QueueReporterInterface::class),
                $this->matchesRegularExpression(self::RUN_ID_PATTERN)
            )
            ->willReturn([5 => 0]);

        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $this->assertTrue($success);
        $this->assertStringContainsString('Store 5 (Fifth Store): completed', $output->fetch());
    }

    public function testOrchestratePrintsSummaryAndFailsWhenAWorkerExitsNonZero(): void
    {
        $storeNameFetcher = $this->createStoreNameFetcher([2 => 'Store 2 (Second Store)', 3 => 'Store 3 (Third Store)']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(QueueOrchestrator::ORCHESTRATOR_LOCK_NAME);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2, 3]);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(2);

        $output = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with(
                [2, 3],
                2,
                $this->isInstanceOf(QueueReporterInterface::class),
                $this->matchesRegularExpression(self::RUN_ID_PATTERN)
            )
            ->willReturn([2 => 0, 3 => 1]);

        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $fetch = $output->fetch();
        $this->assertFalse($success);
        $this->assertStringContainsString('Store 2 (Second Store): completed', $fetch);
        $this->assertStringContainsString('Store 3 (Third Store): failed (exit code 1)', $fetch);
    }

    public function testOrchestrateUsesStoreNameFetcherLabelsInSummary(): void
    {
        $storeNameFetcher = $this->createStoreNameFetcher([2 => 'Store 2 (Store ID 2)']);

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2]);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturnCallback(
            static fn (?int $cliOverride, ?int $pendingStores) => $pendingStores === null ? 4 : 1
        );

        $output = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with(
                [2],
                1,
                $this->isInstanceOf(QueueReporterInterface::class),
                $this->matchesRegularExpression(self::RUN_ID_PATTERN)
            )
            ->willReturn([2 => 1]);

        $success = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createReporter($output));

        $this->assertFalse($success);
        $this->assertStringContainsString('Store 2 (Store ID 2): failed', $output->fetch());
    }

    public function testOrchestrateReleasesLockWhenPoolThrows(): void
    {
        $storeNameFetcher = $this->createStoreNameFetcher([2 => 'Store 2 (Second Store)', 3 => 'Store 3 (Third Store)']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(QueueOrchestrator::ORCHESTRATOR_LOCK_NAME);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2, 3]);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(2);

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->willThrowException(new \RuntimeException('spawn failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('spawn failed');

        $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $this->createStub(ConsoleQueueReporter::class));
    }
}
