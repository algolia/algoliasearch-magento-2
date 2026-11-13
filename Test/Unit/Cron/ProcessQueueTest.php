<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Cron;

use Algolia\AlgoliaSearch\Api\LoggerInterface;
use Algolia\AlgoliaSearch\Cron\ProcessQueue;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Logger\CronLoggerOutput;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
use Algolia\AlgoliaSearch\Test\TestCase;
use Symfony\Component\Console\Output\NullOutput;

class ProcessQueueTest extends TestCase
{
    protected function createObjectToTest(
        ?QueueHelper $queueHelper = null,
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?QueueOrchestrator $queueOrchestrator = null,
        ?CronLoggerOutput $cronLoggerOutput = null,
        ?NullOutput $nullOutput = null,
    ): ProcessQueue {
        return new ProcessQueue(
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
            $queueOrchestrator ?? $this->createStub(QueueOrchestrator::class),
            $cronLoggerOutput ?? $this->createStub(CronLoggerOutput::class),
            $nullOutput ?? new NullOutput(),
        );
    }

    protected function createQueueHelper(bool $isActive, bool $useBuiltInCron, bool $parallel): QueueHelper
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn($isActive);
        $queueHelper->method('useBuiltInCron')->willReturn($useBuiltInCron);
        $queueHelper->method('isParallelProcessingEnabled')->willReturn($parallel);

        return $queueHelper;
    }

    public function testExecuteDoesNothingWhenQueueIsInactive(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: false, useBuiltInCron: true, parallel: false);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $this->createObjectToTest(queueHelper: $queueHelper, queue: $queue, queueOrchestrator: $queueOrchestrator)
            ->execute();
    }

    public function testExecuteDoesNothingWhenBuiltInCronIsDisabled(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: false, parallel: false);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $this->createObjectToTest(queueHelper: $queueHelper, queue: $queue, queueOrchestrator: $queueOrchestrator)
            ->execute();
    }

    public function testLegacyPathDisplaysErrorAndDoesNotRunCronWhenCredentialsAreInvalid(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: false);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(ProcessQueue::class);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
            queueOrchestrator: $queueOrchestrator,
        )->execute();
    }

    public function testLegacyPathRunsCronWhenCredentialsAreValid(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: false);

        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron');

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->never())->method('orchestrate');

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
            queueOrchestrator: $queueOrchestrator,
        )->execute();
    }

    public function testParallelPathOrchestratesWithTheInjectedOutputsAndNeverRunsCronGlobally(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: true);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        // Children gate themselves per store: no global credentials check on this path
        $credentialsManager->expects($this->never())->method('checkCredentialsWithSearchOnlyAPIKey');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $cronLoggerOutput = new CronLoggerOutput($this->createStub(LoggerInterface::class));
        $nullOutput = new NullOutput();

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->with(null, $cronLoggerOutput, $nullOutput);

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
            queueOrchestrator: $queueOrchestrator,
            cronLoggerOutput: $cronLoggerOutput,
            nullOutput: $nullOutput,
        )->execute();
    }

    public function testParallelPathFeedsOrchestratorOutputFromTheAlgoliaLogger(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with('Processing the indexing queue in parallel for 2 store(s) with up to 2 worker(s).');

        $cronLoggerOutput = new CronLoggerOutput($logger);

        $queueOrchestrator = $this->createStub(QueueOrchestrator::class);
        $queueOrchestrator->method('orchestrate')->willReturnCallback(
            function ($override, $output): int {
                $output->writeln(
                    '<info>Processing the indexing queue in parallel for 2 store(s) '
                    . 'with up to 2 worker(s).</info>'
                );

                return 0;
            }
        );

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queueOrchestrator: $queueOrchestrator,
            cronLoggerOutput: $cronLoggerOutput,
        )->execute();
    }
}
