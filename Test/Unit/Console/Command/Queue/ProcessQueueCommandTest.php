<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Model\Queue\WorkerProcessPool;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\StoreNameFetcher;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\App\State;
use Magento\Framework\Console\Cli;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class ProcessQueueCommandTest extends TestCase
{
    protected function createObjectToTest(
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?LockManagerInterface $lockManager = null,
        ?StoreNameFetcher $storeNameFetcher = null,
        ?MaxProcessesResolver $maxProcessesResolver = null,
        ?WorkerProcessPool $workerProcessPool = null,
        bool $spawningSupported = true,
    ): ProcessQueueCommand {
        $credentialsManager ??= $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);
        $workerProcessPool ??= $this->createStub(WorkerProcessPool::class);

        $maxProcessesResolver ??= $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('isProcessSpawningSupported')->willReturn($spawningSupported);

        return new ProcessQueueCommand(
            $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $this->createStub(ConfigHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager,
            $lockManager ?? $this->createStub(LockManagerInterface::class),
            $maxProcessesResolver,
            $workerProcessPool,
        );
    }

    public function testExecuteWithoutStoreIdsRunsLegacyPath(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(1);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();
        $queue->expects($this->never())->method('discoverPendingStoreIds');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');
        $lockManager->expects($this->never())->method('unlock');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteWithoutStoreIdsReportsMissingCredentialsAndDoesNotRun(): void
    {
        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(ProcessQueueCommand::class);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, credentialsManager: $credentialsManager),
            []
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testExecuteProcessesSingleStoreAsWorker(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '2', 0)
            ->willReturn(true);
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 2);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher
            ),
            [2]
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    /**
     * Recursion guard: in worker mode the command only touches the lock manager and
     * Queue::runCron - nothing reachable spawns another process.
     */
    public function testWorkerPathOnlyLocksAndRunsCron(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->expects($this->once())
            ->method('checkCredentialsWithSearchOnlyAPIKey')
            ->with(2)
            ->willReturn(true);
        $credentialsManager->expects($this->never())->method('displayErrorMessage');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->exactly(1))->method('lock')->willReturn(true);
        $lockManager->expects($this->exactly(1))->method('unlock')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(1))->method('runCron')->with(null, false, 2);

        $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                credentialsManager: $credentialsManager,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher
            ),
            [2]
        );
    }

    public function testExecuteProcessesEachStoreWithItsOwnLockInOrder(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $lockIndex = 0;
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->exactly(2))
            ->method('lock')
            ->willReturnCallback(function (string $name) use (&$lockIndex) {
                $this->assertSame(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . [2, 3][$lockIndex++], $name);

                return true;
            });
        $unlockIndex = 0;
        $lockManager->expects($this->exactly(2))
            ->method('unlock')
            ->willReturnCallback(function (string $name) use (&$unlockIndex) {
                $this->assertSame(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . [2, 3][$unlockIndex++], $name);

                return true;
            });

        $runIndex = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))
            ->method('runCron')
            ->willReturnCallback(function ($nbJobs, $force, $storeId) use (&$runIndex) {
                $this->assertNull($nbJobs);
                $this->assertFalse($force);
                $this->assertSame([2, 3][$runIndex++], $storeId);
            });

        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2, 3]
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteSkipsStoreWhenLockIsHeldAndProcessesTheRest(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturnCallback(
            fn(string $name) => $name === ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '2' ? false : true
        );
        // Only the acquired store gets unlocked; the skipped store never touches unlock()
        $lockManager->expects($this->once())
            ->method('unlock')
            ->willReturnCallback(function (string $name) {
                $this->assertSame(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '3', $name);

                return true;
            });

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 3);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2, 3],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Second Store', $output->fetch());
    }

    public function testExecuteUnlocksAndPropagatesNonDatabaseExceptions(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createStub(Queue::class);
        $queue->method('runCron')->willThrowException(new \Exception('run failed'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('run failed');

        $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2]
        );
    }

    public function testExecuteReturnsFailureWhenLegacyPathHitDatabaseException(): void
    {
        $queue = $this->createStub(Queue::class);
        $queue->method('runCron')->willThrowException(new \Zend_Db_Exception('database unavailable'));

        $output = new BufferedOutput();
        $code = $this->executeCommand($this->createObjectToTest(queue: $queue), [], $output);

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('database unavailable', $output->fetch());
    }

    public function testExecuteContinuesRemainingStoresAndReturnsFailureWhenDatabaseExceptionThrown(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        // Every acquired store gets unlocked, including the one whose run failed
        $unlockIndex = 0;
        $lockManager->expects($this->exactly(2))
            ->method('unlock')
            ->willReturnCallback(function (string $name) use (&$unlockIndex) {
                $this->assertSame(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . [2, 3][$unlockIndex++], $name);

                return true;
            });

        $runIndex = 0;
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->exactly(2))
            ->method('runCron')
            ->willReturnCallback(function ($nbJobs, $force, $storeId) use (&$runIndex) {
                $this->assertSame([2, 3][$runIndex++], $storeId);

                if ($storeId === 2) {
                    throw new \Zend_Db_Statement_Exception('lock wait timeout exceeded');
                }
            });

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2, 3],
            $output
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $fetch = $output->fetch();
        $this->assertStringContainsString('Second Store', $fetch);
        $this->assertStringContainsString('lock wait timeout exceeded', $fetch);
    }

    #[DataProvider('invalidStoreIdsProvider')]
    public function testExecuteFailsOnInvalidStoreId(string|int $storeId): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager),
            [$storeId],
            $output
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store ID argument must be an integer', $output->fetch());
    }

    public static function invalidStoreIdsProvider(): array
    {
        return [
            'letters' => ['abc'],
            'zero' => [0],
            'negative' => [-1],
        ];
    }

    public function testExecuteFailsOnUnknownStoreBeforeAnyWork(): void
    {
        $storeNameFetcher = $this->createStub(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')
            ->willThrowException(NoSuchEntityException::singleField('storeId', 42));

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');

        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [42]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testExecuteReportsMissingCredentialsPerStoreAndReturnsFailure(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(ProcessQueueCommand::class, 2);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                credentialsManager: $credentialsManager,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher
            ),
            [2]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testExecuteContinuesRemainingStoresWhenOneStoreMissesCredentials(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')
            ->willReturnCallback(fn(?int $storeId) => $storeId !== 2); // fail creds on 3
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(ProcessQueueCommand::class, 2);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 3);

        // Only the store with valid credentials is locked
        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '3')
            ->willReturn(true);
        $lockManager->method('unlock')->willReturn(true);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                credentialsManager: $credentialsManager,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher
            ),
            [2, 3]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testDefinitionKeepsAliasAndOptionalArrayStoreArgument(): void
    {
        $cmd = $this->createObjectToTest();
        $this->invokeMethod($cmd, 'configure');

        $this->assertContains('algolia:reindex:process_queue', $cmd->getAliases());

        $argument = $cmd->getDefinition()->getArgument('store_id');
        $this->assertTrue($argument->isArray());
        $this->assertFalse($argument->isRequired());
        $this->assertTrue($cmd->getDefinition()->hasOption('max-processes'));
        $this->assertTrue($cmd->getDefinition()->hasOption('sequential'));
    }

    public function testSequentialOptionForcesLegacyPathEvenWithMaxProcesses(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->never())->method('resolve');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output,
            ['--sequential' => true, '--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('takes precedence', $output->fetch());
    }

    public function testExecuteFallsBackToSequentialWhenResolvedMaxProcessesIsOne(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(1);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();
        $queue->expects($this->never())->method('discoverPendingStoreIds');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteFallsBackToSequentialWhenMaxProcessesOptionIsOne(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())->method('resolve')->with(1)->willReturn(1);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output,
            ['--max-processes' => '1']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Maximum parallel queue processes: 1', $output->fetch());
    }

    public function testExecuteFallsBackToSequentialWhenProcessSpawningIsUnsupported(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();
        $queue->expects($this->never())->method('discoverPendingStoreIds');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool,
                spawningSupported: false
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('proc_open is disabled', $output->fetch());
    }

    public function testExecuteSkipsOrchestrationWhenOrchestratorLockIsHeld(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->once())
            ->method('lock')
            ->with(ProcessQueueCommand::ORCHESTRATOR_LOCK_NAME, 0)
            ->willReturn(false);
        // Never acquired, so never unlocked
        $lockManager->expects($this->never())->method('unlock');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('discoverPendingStoreIds');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('already running', $output->fetch());
    }

    public function testExecuteReleasesOrchestratorLockWhenPoolThrows(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(ProcessQueueCommand::ORCHESTRATOR_LOCK_NAME);

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

        $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            []
        );
    }

    public function testExecuteFallsBackToSequentialWhenStorelessJobsArePending(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $queue = $this->createMock(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2, 3]);
        $queue->method('hasStorelessPendingJobs')->willReturn(true);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Store-agnostic indexing jobs are pending', $output->fetch());
    }

    public function testExecuteReportsNoPendingJobsAndSucceeds(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->expects($this->never())->method('getStoreNames');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(ProcessQueueCommand::ORCHESTRATOR_LOCK_NAME);

        $queue = $this->createMock(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([]);
        $queue->method('hasStorelessPendingJobs')->willReturn(false);
        $queue->expects($this->never())->method('runCron');

        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->never())->method('run');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('No pending indexing queue jobs.', $output->fetch());
    }

    public function testExecuteRunsPoolWithDiscoveredStoresAndClampedCap(): void
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
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with($storeIds, 2, $output)
            ->willReturn(array_fill_keys($storeIds, 0));

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                credentialsManager: $credentialsManager,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

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

    public function testExecuteSpawnsSingleWorkerForSinglePendingStore(): void
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
            ->with([5], 1, $output)
            ->willReturn([5 => 0]);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('Store 5 (Fifth Store): completed', $output->fetch());
    }

    public function testExecutePrintsSummaryAndFailsWhenAWorkerExitsNonZero(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2, 3])->willReturn(['Second Store', 'Third Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        $lockManager->expects($this->once())->method('unlock')
            ->with(ProcessQueueCommand::ORCHESTRATOR_LOCK_NAME);

        $queue = $this->createStub(Queue::class);
        $queue->method('discoverPendingStoreIds')->willReturn([2, 3]);

        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(2);

        $output = new BufferedOutput();
        $workerProcessPool = $this->createMock(WorkerProcessPool::class);
        $workerProcessPool->expects($this->once())
            ->method('run')
            ->with([2, 3], 2, $output)
            ->willReturn([2 => 0, 3 => 1]);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $fetch = $output->fetch();
        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store 2 (Second Store): completed', $fetch);
        $this->assertStringContainsString('Store 3 (Third Store): failed (exit code 1)', $fetch);
    }

    public function testExecuteUsesStoreIdLabelsWhenDiscoveredStoreNameFetchFails(): void
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
            ->with([2], 1, $output)
            ->willReturn([2 => 1]);

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                storeNameFetcher: $storeNameFetcher,
                maxProcessesResolver: $maxProcessesResolver,
                workerProcessPool: $workerProcessPool
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('Store 2 (Store ID 2): failed', $output->fetch());
    }

    public function testWorkerModeExitsFailureWhenStoreHadFailedJobs(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);
        // The per-store lock is still released even though the store reports failed jobs
        $lockManager->expects($this->once())->method('unlock')
            ->with(ProcessQueueCommand::STORE_LOCK_NAME_PREFIX . '2');

        $queue = $this->createMock(Queue::class);
        $queue->method('runCron')->with(null, false, 2);
        $queue->method('getNoOfFailedJobs')->with(2)->willReturn(3);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2],
            $output
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('3 indexing job(s) failed for Second Store (ID 2)', $output->fetch());
    }

    public function testWorkerModeSucceedsWhenStoreHadNoFailedJobs(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('getStoreNames')->with([2])->willReturn(['Second Store']);

        $lockManager = $this->createStub(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->with(null, false, 2);
        $queue->method('getNoOfFailedJobs')->with(2)->willReturn(0);

        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue, lockManager: $lockManager, storeNameFetcher: $storeNameFetcher),
            [2]
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecutePassesMaxProcessesOptionToResolverAndPrintsItsResult(): void
    {
        // The stub returns 7 intentionally (a value the command cannot derive itself) so the
        // assertion proves the printed value came from the *resolver*, not any option passed.
        // Spawning is disabled for simplicity (resolver still consulted for mode decision).
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())
            ->method('resolve')
            ->with(4)
            ->willReturn(7);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                maxProcessesResolver: $maxProcessesResolver,
                spawningSupported: false
            ),
            [],
            $output,
            ['--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString(
            'Maximum parallel queue processes: 7',
            $output->fetch(),
            'Output should show the resolver\'s return value (7), not the raw --max-processes option (4)'
        );
    }

    /**
     * The resolver is what decides the mode in the no-arg branch: even without the
     * --max-processes option it must be consulted (admin config / auto-detection).
     */
    public function testExecuteResolvesMaxProcessesWithoutOptionInNoArgMode(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())
            ->method('resolve')
            ->with(null)
            ->willReturn(1);

        $code = $this->executeCommand(
            $this->createObjectToTest(maxProcessesResolver: $maxProcessesResolver),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    #[DataProvider('invalidMaxProcessesProvider')]
    public function testExecuteFailsOnInvalidMaxProcessesOption(string $value): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(queue: $queue),
            [],
            $output,
            ['--max-processes' => $value]
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
        $this->assertStringContainsString('must be an integer greater than or equal to 1', $output->fetch());
    }

    public static function invalidMaxProcessesProvider(): array
    {
        return [
            'zero' => ['0'],
            'negative' => ['-2'],
            'non numeric' => ['many'],
            'decimal' => ['2.5'],
        ];
    }

    private function executeCommand(
        ProcessQueueCommand $cmd,
        array $storeIds,
        ?BufferedOutput $output = null,
        array $options = []
    ): int {
        $this->invokeMethod($cmd, 'configure');

        $input = new ArrayInput(array_merge($options, ['store_id' => $storeIds]));
        $input->bind($cmd->getDefinition());

        return $this->invokeMethod($cmd, 'execute', [
            $input,
            $output ?? new BufferedOutput(),
        ]);
    }
}
