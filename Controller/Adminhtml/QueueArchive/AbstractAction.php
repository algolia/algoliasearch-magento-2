<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\QueueArchive;

use Algolia\AlgoliaSearch\Model\QueueArchiveFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive as QueueArchiveResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Indexer\Model\IndexerFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var \Algolia\AlgoliaSearch\Model\QueueArchiveFactory */
    protected $queueArchiveFactory;

    /** @var QueueArchiveResourceModel */
    protected $queueArchiveResourceModel;

    /** @var IndexerFactory */
    protected $indexerFactory;

    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        QueueArchiveFactory $queueArchiveFactory,
        QueueArchiveResourceModel $queueArchiveResourceModel,
        IndexerFactory $indexerFactory
    ) {
        parent::__construct($context);

        $this->backendSession     = $backendSession;
        $this->queueArchiveFactory       = $queueArchiveFactory;
        $this->queueArchiveResourceModel = $queueArchiveResourceModel;
        $this->indexerFactory   = $indexerFactory;
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    /**
     * @return \Algolia\AlgoliaSearch\Model\QueueArchive
     */
    protected function initJob()
    {
        $jobId = (int) $this->getRequest()->getParam('id');

        // We must have an id
        if (!$jobId) {
            return null;
        }

        /** @var \Algolia\AlgoliaSearch\Model\QueueArchive $model */
        $model = $this->queueArchiveFactory->create();
        $this->queueArchiveResourceModel->load($model, $jobId);
        if (!$model->getId()) {
            return null;
        }

        // Register model to use later in blocks
        $this->backendSession->setData('current_job', $model);

        return $model;
    }
}
