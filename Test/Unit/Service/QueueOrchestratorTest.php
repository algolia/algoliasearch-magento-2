<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service;

use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueOrchestratorTest extends TestCase
{
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

    public function testRunSequentialRunsCronAndSucceeds(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $credentialsManager->expects($this->never())->method('displayErrorMessage');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with();

        $code = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager)
            ->runSequential(new BufferedOutput());

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testRunSequentialReportsMissingCredentialsAndDoesNotRun(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(QueueOrchestrator::class);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $code = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager)
            ->runSequential(new BufferedOutput());

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testRunSequentialReturnsFailureOnDatabaseException(): void
    {
        $queue = $this->createStub(Queue::class);
        $queue->method('runCron')->willThrowException(new \Zend_Db_Exception('database unavailable'));

        $output = new BufferedOutput();
        $code = $this->createObjectToTest(queue: $queue)->runSequential($output);

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('database unavailable', $output->fetch());
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
        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
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
        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Store-agnostic indexing jobs are pending', $output->fetch());
    }

    public function testOrchestrateStorelessFallbackReportsMissingCredentialsAndFails(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(QueueOrchestrator::class);

        $queue = $this->createMock(Queue::class);
        $queue->method('hasStorelessPendingJobs')->willReturn(true);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $code = $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager, lockManager: $lockManager)
            ->orchestrate(null, new BufferedOutput(), new BufferedOutput());

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testOrchestrateReportsNoPendingJobsAndSucceeds(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->never())->method('getStoreNames');

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
        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('No pending indexing queue jobs.', $output->fetch());
    }

    public function testOrchestrateRunsPoolWithDiscoveredStoresAndClampedCap(): void
    {
        $storeIds = [2, 3, 4, 5, 6];
        $storeNames = ['Second Store', 'Third Store', 'Fourth Store', 'Fifth Store', 'Sixth Store'];

        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with($storeIds)->willReturn($storeNames);

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
        $workerOutput = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with($storeIds, 2, $workerOutput)
            ->willReturn(array_fill_keys($storeIds, 0));

        $code = $this->createObjectToTest(
            queue: $queue,
            credentialsManager: $credentialsManager,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, $workerOutput);

        $fetch = $output->fetch();
        $this->assertSame(Cli::RETURN_SUCCESS, $code);
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
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->once())
            ->method('getStoreNames')
            ->with([2])
            ->willReturn(['Second Store']);

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
            ->with([2], 1, $this->isInstanceOf(BufferedOutput::class))
            ->willReturn([2 => 0]);

        $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(7, new BufferedOutput(), new BufferedOutput());
    }

    public function testOrchestrateSpawnsSingleWorkerForSinglePendingStore(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([5])->willReturn(['Fifth Store']);

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
            ->with([5], 1, $this->isInstanceOf(BufferedOutput::class))
            ->willReturn([5 => 0]);

        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Store 5 (Fifth Store): completed', $output->fetch());
    }

    public function testOrchestratePrintsSummaryAndFailsWhenAWorkerExitsNonZero(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

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
            ->with([2, 3], 2, $this->isInstanceOf(BufferedOutput::class))
            ->willReturn([2 => 0, 3 => 1]);

        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $fetch = $output->fetch();
        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store 2 (Second Store): completed', $fetch);
        $this->assertStringContainsString('Store 3 (Third Store): failed (exit code 1)', $fetch);
    }

    public function testOrchestrateUsesStoreIdLabelsWhenDiscoveredStoreNameFetchFails(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 2));

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
            ->with([2], 1, $this->isInstanceOf(BufferedOutput::class))
            ->willReturn([2 => 1]);

        $code = $this->createObjectToTest(
            queue: $queue,
            lockManager: $lockManager,
            storeNameFetcher: $storeNameFetcher,
            maxProcessesResolver: $maxProcessesResolver,
            workerProcessPool: $workerProcessPool
        )->orchestrate(null, $output, new BufferedOutput());

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store 2 (Store ID 2): failed', $output->fetch());
    }

    public function testOrchestrateReleasesLockWhenPoolThrows(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

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
        )->orchestrate(null, new BufferedOutput(), new BufferedOutput());
    }
}
