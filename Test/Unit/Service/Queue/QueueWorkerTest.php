<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Service\Queue;

use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\Output\ConsoleQueueReporter;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcess;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessFactory;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\Queue\QueueOrchestrator;
use Algolia\AlgoliaSearch\Service\Queue\QueueWorker;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Lock\LockManagerInterface;
use Symfony\Component\Console\Output\BufferedOutput;

class QueueWorkerTest extends TestCase
{
    protected function createObjectToTest(
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?LockManagerInterface $lockManager = null,
        ?StoreNameFetcher $storeNameFetcher = null,
    ): QueueWorker {
        $credentialsManager ??= $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        return new QueueWorker(
            $queue ?? $this->createStub(Queue::class),
            $lockManager ?? $this->createStub(LockManagerInterface::class),
            $credentialsManager,
            $storeNameFetcher ?? $this->createStoreNameFetcher(),
        );
    }

    protected function createStoreNameFetcher(): StoreNameFetcher
    {
        $names = [2 => 'Second Store', 3 => 'Third Store'];
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreLabel')->willReturnCallback(
            fn(int $storeId) => 'Store ' . $storeId . ' (' . ($names[$storeId] ?? 'Store ' . $storeId) . ')'
        );

        return $storeNameFetcher;
    }

    protected function createReporter(BufferedOutput $output): ConsoleQueueReporter
    {
        return new ConsoleQueueReporter($output);
    }

    /**
     * Recursion guard: workers never spawn because they are never given anything that can.
     * If this fails, a spawn-capable dependency was injected into QueueWorker.
     */
    public function testConstructorHasNoSpawnCapableDependencies(): void
    {
        $forbidden = [
            QueueOrchestrator::class,
            WorkerProcessPool::class,
            WorkerProcessFactory::class,
            WorkerProcess::class,
            MaxProcessesResolver::class,
        ];

        $dependencies = array_map(
            fn(\ReflectionParameter $p) => $p->getType() instanceof \ReflectionNamedType
                ? $p->getType()->getName()
                : null,
            (new \ReflectionClass(QueueWorker::class))->getConstructor()->getParameters()
        );

        $this->assertSame([], array_values(array_intersect($dependencies, $forbidden)));
    }

    public function testProcessStoresDrainsStoreUnderItsOwnLock(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(QueueWorker::STORE_LOCK_NAME_PREFIX . '2', 0)
            ->willReturn(true);
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(QueueWorker::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 2, null);

        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)
            ->processStores([2], $this->createStub(ConsoleQueueReporter::class));

        $this->assertTrue($success);
    }

    public function testProcessStoresForwardsRunIdToRunCron(): void
    {
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 2, 'abc123run');

        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)
            ->processStores([2], $this->createStub(ConsoleQueueReporter::class), 'abc123run');

        $this->assertTrue($success);
    }

    public function testWorkerPathLocksAndRunsCron(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())
            ->method('checkCredentialsWithSearchOnlyAPIKey')
            ->with(2)
            ->willReturn(true);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->exactly(1))->method('lock')->willReturn(true);
        $lockManager->expects($this->exactly(1))->method('unlock')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(1))->method('runCron')->with(null, false, 2, null);

        $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager, lockManager: $lockManager)
            ->processStores([2], $this->createStub(ConsoleQueueReporter::class));
    }

    public function testProcessStoresProcessesEachStoreWithItsOwnLockInOrder(): void
    {
        $lockIndex = 0;
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->exactly(2))
            ->method('lock')
            ->willReturnCallback(function (string $name) use (&$lockIndex) {
                $this->assertSame(QueueWorker::STORE_LOCK_NAME_PREFIX . [2, 3][$lockIndex++], $name);

                return true;
            });
        $unlockIndex = 0;
        $lockManager->expects($this->exactly(2))
            ->method('unlock')
            ->willReturnCallback(function (string $name) use (&$unlockIndex) {
                $this->assertSame(QueueWorker::STORE_LOCK_NAME_PREFIX . [2, 3][$unlockIndex++], $name);

                return true;
            });

        $runIndex = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))
            ->method('runCron')
            ->willReturnCallback(function ($nbJobs, $force, $storeId, $runId) use (&$runIndex) {
                $this->assertNull($nbJobs);
                $this->assertFalse($force);
                $this->assertSame([2, 3][$runIndex++], $storeId);
                $this->assertNull($runId);
            });

        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)->processStores(
            [2, 3],
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertTrue($success);
    }

    public function testProcessStoresSkipsStoreWhenLockIsHeldAndProcessesTheRest(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturnCallback(
            fn(string $name) => $name === QueueWorker::STORE_LOCK_NAME_PREFIX . '2' ? false : true
        );
        // Only the acquired store gets unlocked; the skipped store never touches unlock()
        $lockManager->expects($this->once())
            ->method('unlock')
            ->willReturnCallback(function (string $name) {
                $this->assertSame(QueueWorker::STORE_LOCK_NAME_PREFIX . '3', $name);

                return true;
            });

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 3, null);

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)->processStores(
            [2, 3],
            $this->createReporter($output)
        );

        $this->assertTrue($success);
        $this->assertStringContainsString(
            'Indexing queue is already being processed for Store 2 (Second Store), skipping.',
            $output->fetch()
        );
    }

    public function testProcessStoresUnlocksAndPropagatesNonDatabaseExceptions(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(QueueWorker::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createStub(Queue::class);
        $queue->method('runCron')->willThrowException(new \Exception('run failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('run failed');

        $this->createObjectToTest(queue: $queue, lockManager: $lockManager)
            ->processStores([2], $this->createStub(ConsoleQueueReporter::class));
    }

    public function testProcessStoresContinuesRemainingStoresAndFailsWhenDatabaseExceptionThrown(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        // Every acquired store gets unlocked, including the one whose run failed
        $unlockIndex = 0;
        $lockManager->expects($this->exactly(2))
            ->method('unlock')
            ->willReturnCallback(function (string $name) use (&$unlockIndex) {
                $this->assertSame(QueueWorker::STORE_LOCK_NAME_PREFIX . [2, 3][$unlockIndex++], $name);

                return true;
            });

        $runIndex = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))
            ->method('runCron')
            ->willReturnCallback(function ($nbJobs, $force, $storeId, $runId) use (&$runIndex) {
                $this->assertSame([2, 3][$runIndex++], $storeId);
                $this->assertNull($runId);

                if ($storeId === 2) {
                    throw new \Zend_Db_Statement_Exception('lock wait timeout exceeded');
                }
            });

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)->processStores(
            [2, 3],
            $this->createReporter($output)
        );

        $this->assertFalse($success);
        $fetch = $output->fetch();
        $this->assertStringContainsString('Second Store', $fetch);
        $this->assertStringContainsString('lock wait timeout exceeded', $fetch);
    }

    public function testProcessStoresReportsMissingCredentialsAndFailsWithoutTouchingTheLock(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        // Reported through the reporter: displayErrorMessage() would bypass it. The wording is the
        // manager's to own, pinned in AlgoliaCredentialsManagerTest, so it is stubbed out here.
        $credentialsManager->expects($this->never())->method('displayErrorMessage');
        $credentialsManager->expects($this->once())
            ->method('getErrorMessage')
            ->with(QueueWorker::class, 2)
            ->willReturn('credentials message from the manager');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(
            queue: $queue,
            credentialsManager: $credentialsManager,
            lockManager: $lockManager
        )->processStores([2], $this->createReporter($output));

        $this->assertFalse($success);
        $this->assertStringContainsString('credentials message from the manager', $output->fetch());
    }

    public function testProcessStoresContinuesRemainingStoresWhenOneStoreMissesCredentials(): void
    {
        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')
            ->willReturnCallback(fn(?int $storeId) => $storeId !== 2); // fail creds on 2
        $credentialsManager->method('getErrorMessage')->willReturn('credentials message from the manager');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 3, null);

        // Only the store with valid credentials is locked
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(QueueWorker::STORE_LOCK_NAME_PREFIX . '3')
            ->willReturn(true);
        $lockManager->method('unlock')->willReturn(true);

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(
            queue: $queue,
            credentialsManager: $credentialsManager,
            lockManager: $lockManager
        )->processStores(
            [2, 3],
            $this->createReporter($output)
        );

        $this->assertFalse($success);
        $this->assertStringContainsString('credentials message from the manager', $output->fetch());
    }

    public function testProcessStoresFailsWhenStoreHadFailedJobs(): void
    {
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        // The per-store lock is still released even though the store reports failed jobs
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(QueueWorker::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createMock(Queue::class);
        $queue->method('runCron')->with(null, false, 2, null);
        $queue->method('getNoOfFailedJobs')->with(2)->willReturn(3);

        $output = new BufferedOutput();
        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)->processStores(
            [2],
            $this->createReporter($output)
        );

        $this->assertFalse($success);
        $this->assertStringContainsString('3 indexing job(s) failed for Store 2 (Second Store)', $output->fetch());
    }

    public function testProcessStoresSucceedsWhenStoreHadNoFailedJobs(): void
    {
        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 2, null);
        $queue->method('getNoOfFailedJobs')->with(2)->willReturn(0);

        $success = $this->createObjectToTest(queue: $queue, lockManager: $lockManager)->processStores(
            [2],
            $this->createStub(ConsoleQueueReporter::class)
        );

        $this->assertTrue($success);
    }
}
