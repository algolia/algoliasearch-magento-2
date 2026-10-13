<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Console\Command\Queue;

use Algolia\AlgoliaSearch\Console\Command\Queue\ProcessQueueCommand;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
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
    private const LOCK_PREFIX = 'algolia_queue_process_store_';

    protected function createObjectToTest(
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?LockManagerInterface $lockManager = null,
        ?StoreNameFetcher $storeNameFetcher = null,
    ): ProcessQueueCommand {
        $credentialsManager ??= $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        return new ProcessQueueCommand(
            $this->createStub(State::class),
            $storeNameFetcher ?? $this->createStub(StoreNameFetcher::class),
            $this->createStub(ConfigHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager,
            $lockManager ?? $this->createStub(LockManagerInterface::class),
        );
    }

    public function testExecuteWithoutStoreIdsRunsLegacyPath(): void
    {
        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron')->withAnyParameters();

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->expects($this->never())->method('lock');
        $lockManager->expects($this->never())->method('unlock');

        $code = $this->executeCommand($this->createObjectToTest(queue: $queue, lockManager: $lockManager), []);

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
            ->with(self::LOCK_PREFIX . '2', 0)
            ->willReturn(true);
        $lockManager->expects($this->once())
            ->method('unlock')
            ->with(self::LOCK_PREFIX . '2');

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
                $this->assertSame(self::LOCK_PREFIX . [2, 3][$lockIndex++], $name);

                return true;
            });
        $unlockIndex = 0;
        $lockManager->expects($this->exactly(2))
            ->method('unlock')
            ->willReturnCallback(function (string $name) use (&$unlockIndex) {
                $this->assertSame(self::LOCK_PREFIX . [2, 3][$unlockIndex++], $name);

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
            fn(string $name) => $name === self::LOCK_PREFIX . '2' ? false : true
        );
        // Only the acquired store gets unlocked; the skipped store never touches unlock()
        $lockManager->expects($this->once())
            ->method('unlock')
            ->willReturnCallback(function (string $name) {
                $this->assertSame(self::LOCK_PREFIX . '3', $name);

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
            ->with(self::LOCK_PREFIX . '2');

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
                $this->assertSame(self::LOCK_PREFIX . [2, 3][$unlockIndex++], $name);

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
            ->with(self::LOCK_PREFIX . '3')
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
    }

    private function executeCommand(
        ProcessQueueCommand $cmd,
        array $storeIds,
        ?BufferedOutput $output = null
    ): int {
        $this->invokeMethod($cmd, 'configure');

        $input = new ArrayInput(['store_id' => $storeIds]);
        $input->bind($cmd->getDefinition());

        return $this->invokeMethod($cmd, 'execute', [
            $input,
            $output ?? new BufferedOutput(),
        ]);
    }
}
