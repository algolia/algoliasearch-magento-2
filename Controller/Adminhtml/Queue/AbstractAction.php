<?php
/**
 * Module Algolia Algoliasearcgh
 */
namespace Algolia\AlgoliaSearch\Controller\Adminhtml\Queue;

use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResourceModel;

abstract class AbstractAction extends \Magento\Backend\App\Action
{
    /**
     * Core registry
     *
     * @var Registry
     */
    protected $coreRegistry;

    /**
     * @var \Algolia\AlgoliaSearch\Model\JobFactory
     */
    protected $jobFactory;

    /**
     * @var JobResourceModel
     */
    protected $jobResourceModel;

    /**
     * AbstractAction constructor.
     *
     * @param Context          $context
     * @param Registry         $coreRegistry
     * @param JobFactory       $jobFactory
     * @param JobResourceModel $jobResourceModel
     */
    public function __construct(
        Context $context,
        Registry $coreRegistry,
        JobFactory $jobFactory,
        JobResourceModel $jobResourceModel
    ) {
        parent::__construct($context);

        $this->coreRegistry     = $coreRegistry;
        $this->jobFactory       = $jobFactory;
        $this->jobResourceModel = $jobResourceModel;
    }

    /**
     * Is it allowed ?
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('Algolia_AlgoliaSearch::manage');
    }

    /**
     * Init the current job
     *
     * @return Algolia\AlgoliaSearch\Model
     */
    protected function initJob()
    {
        // Get the ID
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
