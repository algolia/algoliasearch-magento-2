<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Cron;

use Algolia\AlgoliaSearch\Api\QueueReporterInterface;
use Algolia\AlgoliaSearch\Cron\ProcessQueue;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Model\Queue\Output\ReporterWorkerOutput;
use Algolia\AlgoliaSearch\Model\Queue\Output\ReporterWorkerOutputFactory;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Service\QueueOrchestrator;
use Algolia\AlgoliaSearch\Test\TestCase;

class ProcessQueueTest extends TestCase
{
    protected function createObjectToTest(
        ?QueueHelper $queueHelper = null,
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
        ?QueueOrchestrator $queueOrchestrator = null,
        ?QueueReporterInterface $queueReporter = null,
        ?ReporterWorkerOutputFactory $reporterWorkerOutputFactory = null,
    ): ProcessQueue {
        return new ProcessQueue(
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
            $queueOrchestrator ?? $this->createStub(QueueOrchestrator::class),
            $queueReporter ?? $this->createStub(QueueReporterInterface::class),
            $reporterWorkerOutputFactory ?? $this->createReporterWorkerOutputFactory(
                $queueReporter ?? $this->createStub(QueueReporterInterface::class)
            ),
        );
    }

    /**
     * Mirrors the Magento-generated factory: create() injects the passed reporter,
     * so the adapter wraps the cron's own injected reporter instance
     */
    private function createReporterWorkerOutputFactory(
        QueueReporterInterface $reporter
    ): ReporterWorkerOutputFactory {
        $factory = $this->createStub(ReporterWorkerOutputFactory::class);
        $factory->method('create')->willReturnCallback(
            fn (array $data = []) => new ReporterWorkerOutput($data['reporter'] ?? $reporter)
        );

        return $factory;
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

    /**
     * TODO: Revisit this logic - is this check necessary since creds are handled per store?
     */
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

    public function testParallelPathOrchestratesWithAnAdapterWrappingTheInjectedReporter(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: true);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        // Children gate themselves per store: no global credentials check on this path
        $credentialsManager->expects($this->never())->method('checkCredentialsWithSearchOnlyAPIKey');

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $queueReporter = $this->createMock(QueueReporterInterface::class);
        $queueReporter->expects($this->once())->method('log')->with('[Store 2 (English)] worker line');

        $reporterWorkerOutputFactory = $this->createReporterWorkerOutputFactory($queueReporter);

        $queueOrchestrator = $this->createMock(QueueOrchestrator::class);
        $queueOrchestrator->expects($this->once())
            ->method('orchestrate')
            ->with(
                null,
                $this->identicalTo($queueReporter),
                $this->isInstanceOf(ReporterWorkerOutput::class)
            )
            ->willReturnCallback(function ($override, QueueReporterInterface $reporter, $workerOutput): int {
                $workerOutput->writeln('[Store 2 (English)] worker line');

                return 0;
            });

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
            queueOrchestrator: $queueOrchestrator,
            queueReporter: $queueReporter,
            reporterWorkerOutputFactory: $reporterWorkerOutputFactory,
        )->execute();
    }

    public function testParallelPathFeedsOrchestratorReportingThroughTheInjectedReporter(): void
    {
        $queueHelper = $this->createQueueHelper(isActive: true, useBuiltInCron: true, parallel: true);

        $queueReporter = $this->createMock(QueueReporterInterface::class);
        $queueReporter->expects($this->once())
            ->method('info')
            ->with('Processing the indexing queue in parallel for 2 store(s) with up to 2 worker(s).');

        $queueOrchestrator = $this->createStub(QueueOrchestrator::class);
        $queueOrchestrator->method('orchestrate')->willReturnCallback(
            function ($override, QueueReporterInterface $reporter): int {
                $reporter->info(
                    'Processing the indexing queue in parallel for 2 store(s) '
                    . 'with up to 2 worker(s).'
                );

                return 0;
            }
        );

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queueOrchestrator: $queueOrchestrator,
            queueReporter: $queueReporter,
        )->execute();
    }
}
