<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Helper\Configuration\QueueHelper;
use Algolia\AlgoliaSearch\Model\Indexer\QueueRunner;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;

class Status extends Template
{
    public const int CRON_QUEUE_FREQUENCY = 330;

    public const int QUEUE_NOT_PROCESSED_LIMIT = 3600;

    public const int QUEUE_FAST_LIMIT = 220;

    protected ?Indexer $queueRunnerIndexer = null;

    public function __construct(
        Context $context,
        protected IndexerFactory $indexerFactory,
        protected DateTime $dateTime,
        protected QueueHelper $queueHelper,
        protected Queue $queue,
        array $data = []
    ) {
        parent::__construct($context, $data);

        if ($this->isQueueActive()) {
            $this->queueRunnerIndexer = $this->indexerFactory->create();
            $this->queueRunnerIndexer->load(QueueRunner::INDEXER_ID);
        }
    }

    public function isQueueActive(): bool
    {
        return $this->queueHelper->isQueueActive();
    }

    public function getQueueRunnerStatus(): string
    {
        $status = 'unknown';
        $status = match ($this->queueRunnerIndexer->getStatus()) {
            \Magento\Framework\Indexer\StateInterface::STATUS_VALID => 'Ready',
            \Magento\Framework\Indexer\StateInterface::STATUS_INVALID => 'Reindex required',
            \Magento\Framework\Indexer\StateInterface::STATUS_WORKING => 'Processing',
            default => $status,
        };

        return $status;
    }

    public function getLastQueueUpdate(): string
    {
        return $this->queueRunnerIndexer->getLatestUpdated();
    }

    public function getResetQueueUrl(): string
    {
        return $this->getUrl('*/*/reset');
    }

    public function getNotices(): array
    {
        $notices = [];

        if ($this->isQueueStuck()) {
            $notices[] = '<a href="' . $this->getResetQueueUrl() . '"> ' . __('Reset queue') . '</a>';
        }

        if ($this->isQueueNotProcessed()) {
            $notices[] =  __(
                'Queue has not been processed for one hour and indexing might be stuck or you cron is not set up properly.'
            );
            $notices[] =  __(
                'To help you, please read our <a href="%1" target="_blank">documentation</a>.',
                'https://www.algolia.com/doc/integration/magento-2/how-it-works/indexing-queue/'
            );
        }

        if ($this->isQueueFast()) {
            $notices[] = __(
                'The average processing time of the queue has been performed under 3 minutes.'
            );
            $notices[] = __(
                'Adding more jobs in <a href="%1">the extension configuration</a> would increase the indexing speed.',
                $this->getUrl('adminhtml/system_config/edit/section/algoliasearch_queue')
            );
        }

        return $notices;
    }

    /**
     * If the queue status is not "ready" and it is running for more than 5 minutes, we consider that the queue is stuck
     */
    protected function isQueueStuck(): bool
    {
        if ($this->queueRunnerIndexer->getStatus() === \Magento\Framework\Indexer\StateInterface::STATUS_VALID) {
            return false;
        }

        if ($this->getTimeSinceLastIndexerUpdate() > self::CRON_QUEUE_FREQUENCY) {
            return true;
        }

        return false;
    }

    /**
     * Check if the queue indexer has not been processed for more than 1 hour
     */
    protected function isQueueNotProcessed(): bool
    {
        return $this->getTimeSinceLastIndexerUpdate() > self::QUEUE_NOT_PROCESSED_LIMIT;
    }

    /**
     * Check if the average processing time  of the queue is fast
     */
    protected function isQueueFast(): bool
    {
        $averageProcessingTime = $this->queue->getAverageProcessingTime();

        return $averageProcessingTime !== null && $averageProcessingTime < self::QUEUE_FAST_LIMIT;
    }

    protected function getIndexerLastUpdateTimestamp(): int
    {
        return (int) $this->dateTime->gmtTimestamp($this->queueRunnerIndexer->getLatestUpdated());
    }

    protected function getTimeSinceLastIndexerUpdate(): int
    {
        $now = $this->dateTime->gmtTimestamp('now');

        return $now ? $now - $this->getIndexerLastUpdateTimestamp() : 0;
    }
}
