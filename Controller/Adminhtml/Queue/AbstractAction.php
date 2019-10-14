<?php

namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Indexer\Model\IndexerFactory;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /** @var Registry */
    protected $coreRegistry;

    /** @var \Algolia\AlgoliaSearch\Model\JobFactory */
    protected $jobFactory;

    /** @var JobResourceModel */
    protected $jobResourceModel;

    /** @var IndexerFactory */
    protected $indexerFactory;

    protected $db;

    protected $tableName;

    /**
     * @param Context          $context
     * @param Registry         $coreRegistry
     * @param JobFactory       $jobFactory
     * @param JobResourceModel $jobResourceModel
     * @param IndexerFactory   $indexerFactory
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        JobFactory $jobFactory,
        JobResourceModel $jobResourceModel,
        IndexerFactory $indexerFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);

        $this->coreRegistry     = $coreRegistry;
        $this->jobFactory       = $jobFactory;
        $this->jobResourceModel = $jobResourceModel;
        $this->indexerFactory   = $indexerFactory;
        $this->db               = $resourceConnection->getConnection('core_write');
        $this->tableName        = $resourceConnection->getTableName('algoliasearch_queue');
    }

    /** @return bool */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    /** @return \Algolia\AlgoliaSearch\Model */
    protected function initJob()
    {
        $jobId = (int) $this->getRequest()->getParam('id');

        // We must have an id
        if (!$jobId) {
            return null;
        }

        /** @var \Algolia\AlgoliaSearch\Model $model */
        $model = $this->jobFactory->create();
        $this->jobResourceModel->load($model, $jobId);
        if (!$model->getId()) {
            return null;
        }

        // Register model to use later in blocks
        $this->coreRegistry->register('current_job', $model);

        return $model;
    }
}
