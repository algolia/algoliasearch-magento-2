<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\MaxProcessesResolver;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
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
        ?QueueOrchestrator $queueOrchestrator = null,
        bool $spawningSupported = true,
    ): ProcessQueueCommand {
        $credentialsManager ??= $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $maxProcessesResolver ??= $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('isProcessSpawningSupported')->willReturn($spawningSupported);

        $queueOrchestrator ??= $this->createStub(QueueOrchestrator::class);
        $queueOrchestrator->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);
        $queueOrchestrator->method('orchestrate')->willReturn(Cli::RETURN_SUCCESS);

        return new ProcessQueueCommand(
            $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $this->createStub(ConfigHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager,
            $lockManager ?? $this->createStub(LockManagerInterface::class),
            $maxProcessesResolver,
            $queueOrchestrator,
        );
    }

    public function testExecuteWithoutStoreIdsRunsSequentialPath(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(1);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron')->withAnyParameters();
        $queue->expects($this->never())->method('discoverPendingStoreIds');

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');
        $lockManager->expects($this->never())->method('unlock');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                queue: $queue,
                lockManager: $lockManager,
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecutePropagatesSequentialFailure(): void
    {
        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('runSequential')
            ->willReturn(Cli::RETURN_FAILURE);

        $code = $this->executeCommand(
            $this->createObjectToTest(queueOrchestrator: $queueOrchestrator),
            []
        );

        $this->assertSame(Cli::RETURN_FAILURE, $code);
    }

    public function testExecuteProcessesSingleStoreAsWorker(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2, 3], true)
            ->willReturn([2 => 'Second Store', 3 => 'Third Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2, 3], true)
            ->willReturn([2 => 'Second Store', 3 => 'Third Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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

    public function testExecuteContinuesRemainingStoresAndReturnsFailureWhenDatabaseExceptionThrown(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')->with([2, 3], true)
            ->willReturn([2 => 'Second Store', 3 => 'Third Store']);

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
        $storeNameFetcher->method('resolveStoreNames')
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
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2, 3], true)
            ->willReturn([2 => 'Second Store', 3 => 'Third Store']);

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

    public function testSequentialOptionForcesSequentialPathEvenWithMaxProcesses(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->never())->method('resolve');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
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

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            []
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
    }

    public function testExecuteFallsBackToSequentialWhenMaxProcessesOptionIsOne(): void
    {
        $maxProcessesResolver = $this->createMock(MaxProcessesResolver::class);
        $maxProcessesResolver->expects($this->once())->method('resolve')->with(1)->willReturn(1);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
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

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator,
                spawningSupported: false
            ),
            [],
            $output
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertStringContainsString('proc_open is disabled', $output->fetch());
    }

    public function testExecuteDelegatesOrchestrationWithOverrideAndOutput(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(4);

        $output = new BufferedOutput();
        $capturedArgs = [];
        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->willReturnCallback(function (...$args) use (&$capturedArgs) {
                $capturedArgs = $args;

                return Cli::RETURN_SUCCESS;
            });

        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            [],
            $output,
            ['--max-processes' => '4']
        );

        $this->assertSame(Cli::RETURN_SUCCESS, $code);
        $this->assertSame(4, $capturedArgs[0]);
        $this->assertSame($output, $capturedArgs[1], 'Orchestrator own-line output must be the command output');
        $this->assertSame($output, $capturedArgs[2], 'Worker output must be the command output');
    }

    public function testExecutePropagatesOrchestratorExceptions(): void
    {
        $maxProcessesResolver = $this->createStub(MaxProcessesResolver::class);
        $maxProcessesResolver->method('resolve')->willReturn(2);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->willThrowException(new \RuntimeException('spawn failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('spawn failed');

        $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator
            ),
            []
        );
    }

    public function testWorkerModeExitsFailureWhenStoreHadFailedJobs(): void
    {
        $storeNameFetcher = $this->createMock(StoreNameFetcher::class);
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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
        $storeNameFetcher->method('resolveStoreNames')->with([2], true)->willReturn([2 => 'Second Store']);

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

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())->method('runSequential')->willReturn(Cli::RETURN_SUCCESS);

        $output = new BufferedOutput();
        $code = $this->executeCommand(
            $this->createObjectToTest(
                maxProcessesResolver: $maxProcessesResolver,
                queueOrchestrator: $queueOrchestrator,
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
