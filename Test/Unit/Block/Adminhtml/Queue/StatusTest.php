<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Block\Adminhtml\Queue\Status;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;
use PHPUnit\Framework\Attributes\DataProvider;

class StatusTest extends TestCase
{
    protected function createObjectToTest(
        ?Indexer $queueRunnerIndexer = null,
        ?Queue $queue = null,
        ?DateTime $dateTime = null,
    ): Status {
        $configHelper = $this->createStub(ConfigHelper::class);
        $configHelper->method('isQueueActive')->willReturn(true);

        $indexerFactory = $this->createStub(IndexerFactory::class);
        $indexerFactory->method('create')->willReturn(
            $queueRunnerIndexer ?? $this->createStub(Indexer::class)
        );

        $urlBuilder = $this->createStub(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturn('http://example.com/reset');

        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($urlBuilder);

        return new Status(
            $context,
            $indexerFactory,
            $dateTime ?? $this->createStub(DateTime::class),
            $configHelper,
            $queue ?? $this->createStub(Queue::class),
        );
    }

    #[DataProvider('queueRunnerStatusDataProvider')]
    public function testGetQueueRunnerStatusReturnsExpectedLabel(string $status, string $expected): void
    {
        $queueRunnerIndexer = $this->createStub(Indexer::class);
        $queueRunnerIndexer->method('getStatus')->willReturn($status);

        $block = $this->createObjectToTest(queueRunnerIndexer: $queueRunnerIndexer);

        $this->assertSame($expected, $block->getQueueRunnerStatus());
    }

    public static function queueRunnerStatusDataProvider(): array
    {
        return [
            'valid'   => [StateInterface::STATUS_VALID,   'Ready'],
            'invalid' => [StateInterface::STATUS_INVALID, 'Reindex required'],
            'working' => [StateInterface::STATUS_WORKING, 'Processing'],
            'unknown' => ['anything_else',                'unknown'],
        ];
    }

    public function testGetNoticesReturnsEmptyArrayWhenNoConditionsMet(): void
    {
        $queueRunnerIndexer = $this->createStub(Indexer::class);
        $queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn($arg) => $arg === 'now' ? 100 : 0);

        $queue = $this->createStub(Queue::class);
        $queue->method('getAverageProcessingTime')->willReturn(null);

        $block = $this->createObjectToTest(queueRunnerIndexer: $queueRunnerIndexer, queue: $queue, dateTime: $dateTime);

        $this->assertSame([], $block->getNotices());
    }

    public function testGetNoticesIncludesResetLinkWhenQueueIsStuck(): void
    {
        // Status != VALID and delta > CRON_QUEUE_FREQUENCY (330) but < QUEUE_NOT_PROCESSED_LIMIT (3600)
        $queueRunnerIndexer = $this->createStub(Indexer::class);
        $queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_INVALID);

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn($arg) => $arg === 'now' ? 500 : 0);

        $queue = $this->createStub(Queue::class);
        $queue->method('getAverageProcessingTime')->willReturn(null);

        $block = $this->createObjectToTest(queueRunnerIndexer: $queueRunnerIndexer, queue: $queue, dateTime: $dateTime);

        $notices = $block->getNotices();

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('Reset queue', $notices[0]);
    }

    public function testGetNoticesIncludesNotProcessedWarningWhenQueueIsStale(): void
    {
        // VALID status so not stuck, but delta > QUEUE_NOT_PROCESSED_LIMIT (3600)
        $queueRunnerIndexer = $this->createStub(Indexer::class);
        $queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn($arg) => $arg === 'now' ? 5000 : 0);

        $queue = $this->createStub(Queue::class);
        $queue->method('getAverageProcessingTime')->willReturn(null);

        $block = $this->createObjectToTest(queueRunnerIndexer: $queueRunnerIndexer, queue: $queue, dateTime: $dateTime);

        $notices = $block->getNotices();

        $this->assertCount(2, $notices);
        $this->assertStringContainsString('Queue has not been processed', (string) $notices[0]);
    }

    public function testGetNoticesIncludesPerformanceSuggestionWhenQueueIsFast(): void
    {
        // delta < CRON_QUEUE_FREQUENCY so not stuck, avg < QUEUE_FAST_LIMIT (220)
        $queueRunnerIndexer = $this->createStub(Indexer::class);
        $queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);

        $dateTime = $this->createStub(DateTime::class);
        $dateTime->method('gmtTimestamp')->willReturnCallback(fn($arg) => $arg === 'now' ? 100 : 0);

        $queue = $this->createStub(Queue::class);
        $queue->method('getAverageProcessingTime')->willReturn(100.0);

        $block = $this->createObjectToTest(queueRunnerIndexer: $queueRunnerIndexer, queue: $queue, dateTime: $dateTime);

        $notices = $block->getNotices();

        $this->assertCount(2, $notices);
        $this->assertStringContainsString('average processing time', (string) $notices[0]);
    }
}
