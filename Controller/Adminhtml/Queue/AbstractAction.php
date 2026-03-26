<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Session\SessionManagerInterface;
use Magento\Indexer\Model\IndexerFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var SessionManagerInterface */
    protected $backendSession;

    /** @var \Algolia\AlgoliaSearch\Model\JobFactory */
    protected $jobFactory;

    /** @var JobResourceModel */
    protected $jobResourceModel;

    /** @var IndexerFactory */
    protected $indexerFactory;

    public function __construct(
        Context $context,
        SessionManagerInterface $backendSession,
        JobFactory $jobFactory,
        JobResourceModel $jobResourceModel,
        IndexerFactory $indexerFactory
    ) {
        parent::__construct($context);

        $this->backendSession   = $backendSession;
        $this->jobFactory       = $jobFactory;
        $this->jobResourceModel = $jobResourceModel;
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
     * @return \Algolia\AlgoliaSearch\Model\Job
     */
    protected function initJob()
    {
        $jobId = (int) $this->getRequest()->getParam('id');

        // We must have an id
        if (!$jobId) {
            return null;
        }

        /** @var \Algolia\AlgoliaSearch\Model\Job $model */
        $model = $this->jobFactory->create();
        $this->jobResourceModel->load($model, $jobId);
        if (!$model->getId()) {
            return null;
        }

        // Register model to use later in blocks
        $this->backendSession->setData('current_job', $model);

        return $model;
    }
}
