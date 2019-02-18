<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Helper\ConfigHelper;
use Algolia\AlgoliaSearch\Model\Queue;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;

class Status extends Template
{
    const CRON_QUEUE_FREQUENCY = 330;

    const QUEUE_NOT_PROCESSED_LIMIT = 3600;

    const QUEUE_FAST_LIMIT = 220;

    /** @var IndexerFactory */
    private $indexerFactory;

    /** @var DateTime */
    private $dateTime;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Queue */
    private $queue;

    /** @var Indexer */
    private $queueRunnerIndexer;

    /**
     * @param Context        $context
     * @param IndexerFactory $indexerFactory
     * @param DateTime       $dateTime
     * @param ConfigHelper   $configHelper
     * @param Queue          $queue
     * @param array          $data
     */
    public function __construct(
        Context $context,
        IndexerFactory $indexerFactory,
        DateTime $dateTime,
        ConfigHelper $configHelper,
        Queue $queue,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->indexerFactory = $indexerFactory;
        $this->dateTime = $dateTime;
        $this->configHelper = $configHelper;
        $this->queue = $queue;

        if ($this->isQueueActive()) {
            $this->queueRunnerIndexer = $this->indexerFactory->create();
            $this->queueRunnerIndexer->load(\Algolia\AlgoliaSearch\Model\Indexer\QueueRunner::INDEXER_ID);
        }
    }

    public function isQueueActive()
    {
        return $this->configHelper->isQueueActive();
    }

    public function getQueueRunnerStatus()
    {
        $status = 'unknown';
        switch ($this->queueRunnerIndexer->getStatus()) {
            case \Magento\Framework\Indexer\StateInterface::STATUS_VALID:
                $status = 'Ready';
                break;
            case \Magento\Framework\Indexer\StateInterface::STATUS_INVALID:
                $status = 'Reindex required';
                break;
            case \Magento\Framework\Indexer\StateInterface::STATUS_WORKING:
                $status = 'Processing';
                break;
        }

        return $status;
    }

    public function getLastQueueUpdate()
    {
        return $this->queueRunnerIndexer->getLatestUpdated();
    }

    public function getResetQueueUrl()
    {
        return $this->getUrl('*/*/reset');
    }

    public function getNotices()
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
     *
     * @return bool
     */
    private function isQueueStuck()
    {
        if ($this->queueRunnerIndexer->getStatus() == \Magento\Framework\Indexer\StateInterface::STATUS_VALID) {
            return false;
        }

        if ($this->getTimeSinceLastIndexerUpdate() > self::CRON_QUEUE_FREQUENCY) {
            return true;
        }

        return false;
    }

    /**
     * Check if the queue indexer has not been processed for more than 1 hour
     *
     * @return bool
     */
    private function isQueueNotProcessed()
    {
        return $this->getTimeSinceLastIndexerUpdate() > self::QUEUE_NOT_PROCESSED_LIMIT;
    }

    /**
     * Check if the average processing time  of the queue is fast
     *
     * @return bool
     */
    private function isQueueFast()
    {
        $averageProcessingTime = $this->queue->getAverageProcessingTime();

        return !is_null($averageProcessingTime) && $averageProcessingTime < self::QUEUE_FAST_LIMIT;
    }

    /** @return int */
    private function getIndexerLastUpdateTimestamp()
    {
        return $this->dateTime->gmtTimestamp($this->queueRunnerIndexer->getLatestUpdated());
    }

    /** @return int */
    private function getTimeSinceLastIndexerUpdate()
    {
        return $this->dateTime->gmtTimestamp('now') - $this->getIndexerLastUpdateTimestamp();
    }
}
