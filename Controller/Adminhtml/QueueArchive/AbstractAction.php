<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\QueueArchive;

use Algolia\AlgoliaSearch\Model\QueueArchive;
use Algolia\AlgoliaSearch\Model\QueueArchiveFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\QueueArchive as QueueArchiveResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Indexer\Model\IndexerFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    public function __construct(
        Context                             $context,
        protected QueueArchiveFactory        $queueArchiveFactory,
        protected QueueArchiveResourceModel  $queueArchiveResourceModel,
        protected IndexerFactory             $indexerFactory
    ) {
        parent::__construct($context);
    }

    /**
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    protected function initJob(): ?QueueArchive
    {
        $jobId = (int) $this->getRequest()->getParam('id');

        // We must have an id
        if (!$jobId) {
            return null;
        }

        /** @var QueueArchive $model */
        $model = $this->queueArchiveFactory->create();
        $this->queueArchiveResourceModel->load($model, $jobId);
        if (!$model->getId()) {
            return null;
        }

        return $model;
    }
}
