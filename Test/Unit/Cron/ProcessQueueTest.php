<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Cron;

use Algolia\AlgoliaSearch\Cron\ProcessQueue;
use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Service\AlgoliaCredentialsManager;
use Algolia\AlgoliaSearch\Test\TestCase;

class ProcessQueueTest extends TestCase
{
    protected function createObjectToTest(
        ?QueueHelper $queueHelper = null,
        ?Queue $queue = null,
        ?AlgoliaCredentialsManager $credentialsManager = null,
    ): ProcessQueue {
        return new ProcessQueue(
            $queueHelper ?? $this->createStub(QueueHelper::class),
            $queue ?? $this->createStub(Queue::class),
            $credentialsManager ?? $this->createStub(AlgoliaCredentialsManager::class),
        );
    }

    public function testExecuteDoesNothingWhenQueueIsInactive(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(false);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $this->createObjectToTest(queueHelper: $queueHelper, queue: $queue)->execute();
    }

    public function testExecuteDoesNothingWhenBuiltInCronIsDisabled(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(true);
        $queueHelper->method('useBuiltInCron')->willReturn(false);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $this->createObjectToTest(queueHelper: $queueHelper, queue: $queue)->execute();
    }

    public function testExecuteDisplaysErrorAndDoesNotRunCronWhenCredentialsAreInvalid(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(true);
        $queueHelper->method('useBuiltInCron')->willReturn(true);

        $credentialsManager = $this->createMock(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(false);
        $credentialsManager->expects($this->once())
            ->method('displayErrorMessage')
            ->with(ProcessQueue::class);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->never())->method('runCron');

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
        )->execute();
    }

    public function testExecuteRunsCronWhenQueueIsActiveAndCredentialsAreValid(): void
    {
        $queueHelper = $this->createStub(QueueHelper::class);
        $queueHelper->method('isQueueActive')->willReturn(true);
        $queueHelper->method('useBuiltInCron')->willReturn(true);

        $credentialsManager = $this->createStub(AlgoliaCredentialsManager::class);
        $credentialsManager->method('checkCredentialsWithSearchOnlyAPIKey')->willReturn(true);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())->method('runCron');

        $this->createObjectToTest(
            queueHelper: $queueHelper,
            queue: $queue,
            credentialsManager: $credentialsManager,
        )->execute();
    }
}
