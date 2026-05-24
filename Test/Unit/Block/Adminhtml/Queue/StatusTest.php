<?php

declare(strict_types=1);

namespace Algolia\AlgoliaSearch\Test\Unit\Block\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Block\Adminhtml\Queue\Status;
use Algolia\AlgoliaSearch\Model\Queue;
use Algolia\AlgoliaSearch\Test\TestCase;
use Magento\Framework\Indexer\StateInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Indexer\Model\Indexer;
use PHPUnit\Framework\MockObject\MockObject;

class StatusTest extends TestCase
{
    protected null|(Status&MockObject) $block = null;
    protected null|(Indexer&MockObject) $queueRunnerIndexer = null;
    protected null|(Queue&MockObject) $queue = null;
    protected null|(DateTime&MockObject) $dateTime = null;

    protected function setUp(): void
    {
        $this->queueRunnerIndexer = $this->createMock(Indexer::class);
        $this->queue = $this->createMock(Queue::class);
        $this->dateTime = $this->createMock(DateTime::class);

        $this->block = $this->getMockBuilder(Status::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getUrl'])
            ->getMock();

        $this->block->method('getUrl')->willReturn('http://example.com/reset');

        $this->setPrivateProperty($this->block, 'queueRunnerIndexer', $this->queueRunnerIndexer);
        $this->setPrivateProperty($this->block, 'queue', $this->queue);
        $this->setPrivateProperty($this->block, 'dateTime', $this->dateTime);
    }

    /**
     * @dataProvider queueRunnerStatusDataProvider
     */
    public function testGetQueueRunnerStatusReturnsExpectedLabel(string $status, string $expected): void
    {
        $this->queueRunnerIndexer->method('getStatus')->willReturn($status);

        $this->assertSame($expected, $this->block->getQueueRunnerStatus());
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
        $this->queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);
        $this->dateTime->method('gmtTimestamp')
            ->willReturnCallback(fn($arg) => $arg === 'now' ? 100 : 0);
        $this->queue->method('getAverageProcessingTime')->willReturn(null);

        $this->assertSame([], $this->block->getNotices());
    }

    public function testGetNoticesIncludesResetLinkWhenQueueIsStuck(): void
    {
        // Status != VALID and delta > CRON_QUEUE_FREQUENCY (330) but < QUEUE_NOT_PROCESSED_LIMIT (3600)
        $this->queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_INVALID);
        $this->dateTime->method('gmtTimestamp')
            ->willReturnCallback(fn($arg) => $arg === 'now' ? 500 : 0);
        $this->queue->method('getAverageProcessingTime')->willReturn(null);

        $notices = $this->block->getNotices();

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('Reset queue', $notices[0]);
    }

    public function testGetNoticesIncludesNotProcessedWarningWhenQueueIsStale(): void
    {
        // VALID status so not stuck, but delta > QUEUE_NOT_PROCESSED_LIMIT (3600)
        $this->queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);
        $this->dateTime->method('gmtTimestamp')
            ->willReturnCallback(fn($arg) => $arg === 'now' ? 5000 : 0);
        $this->queue->method('getAverageProcessingTime')->willReturn(null);

        $notices = $this->block->getNotices();

        $this->assertCount(2, $notices);
        $this->assertStringContainsString('Queue has not been processed', (string) $notices[0]);
    }

    public function testGetNoticesIncludesPerformanceSuggestionWhenQueueIsFast(): void
    {
        // delta < CRON_QUEUE_FREQUENCY so not stuck, avg < QUEUE_FAST_LIMIT (220)
        $this->queueRunnerIndexer->method('getStatus')->willReturn(StateInterface::STATUS_VALID);
        $this->dateTime->method('gmtTimestamp')
            ->willReturnCallback(fn($arg) => $arg === 'now' ? 100 : 0);
        $this->queue->method('getAverageProcessingTime')->willReturn(100.0);

        $notices = $this->block->getNotices();

        $this->assertCount(2, $notices);
        $this->assertStringContainsString('average processing time', (string) $notices[0]);
    }
}
