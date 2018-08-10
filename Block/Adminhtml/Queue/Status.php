<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Queue;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Indexer\Model\Indexer;
use Magento\Indexer\Model\IndexerFactory;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Algolia\AlgoliaSearch\Helper\ConfigHelper;

class Status extends Template
{
    const CRON_QUEUE_FREQUENCY = 300;

    /** @var IndexerFactory */
    private $indexerFactory;

    /** @var DateTime */
    private $dateTime;

    /** @var ConfigHelper */
    private $configHelper;

    /** @var Indexer */
    private $queueRunnerIndexer;

    /**
     * @param Context        $context
     * @param IndexerFactory $indexerFactory
     * @param DateTime       $dateTime
     * @param ConfigHelper   $configHelper
     * @param array          $data
     */
    public function __construct(
        Context $context,
        IndexerFactory $indexerFactory,
        DateTime $dateTime,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->indexerFactory = $indexerFactory;
        $this->dateTime = $dateTime;
        $this->configHelper = $configHelper;

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

    /**
     * If the queue status is not "ready" and it is running for more than 5 minutes, we consider that the queue is stuck
     *
     * @return boolean
     */
    public function isQueueStuck()
    {
        if ($this->queueRunnerIndexer->getStatus() == \Magento\Framework\Indexer\StateInterface::STATUS_VALID) {
            return false;
        }

        $start = $this->dateTime->gmtTimestamp($this->queueRunnerIndexer->getLatestUpdated());
        $end = $this->dateTime->gmtTimestamp('now');
        $diff = $end - $start;

        if ($diff > self::CRON_QUEUE_FREQUENCY) {
            return true;
        }

        return false;
    }
}
