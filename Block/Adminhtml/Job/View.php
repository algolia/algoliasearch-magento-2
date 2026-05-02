<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Job;

use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResource;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    /** @var JobResource */
    protected $jobResource;

    /** @var JobFactory */
    protected $jobFactory;

    /**
     * @param Context $context
     * @param JobResource $jobResource
     * @param JobFactory $jobFactory
     * @param array $data
     */
    public function __construct(
        Context       $context,
        JobResource   $jobResource,
        JobFactory    $jobFactory,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->jobResource = $jobResource;
        $this->jobFactory = $jobFactory;
    }

    /** @inheritdoc */
    protected function _prepareLayout()
    {
        /** @var Button $button */
        $button = $this->getLayout()->createBlock(Button::class);
        $button->setData(
            [
                'label' => __('Back to job list'),
                'onclick' => 'setLocation(\'' . $this->getBackUrl() . '\')',
                'class' => 'back',
            ]
        );

        $this->getToolbar()->setChild('back_button', $button);

        return parent::_prepareLayout();
    }

    /** @return \Algolia\AlgoliaSearch\Model\Job */
    public function getCurrentJob()
    {
        $currentJobId = $this->_request->getParam('id');

        $job = $this->jobFactory->create();
        $this->jobResource->load($job, $currentJobId);

        return $job;
    }

    /**  @return string */
    public function getBackUrl()
    {
        return $this->getUrl('*/*/index');
    }

    /**
     * Return toolbar block instance
     *
     * @return bool|\Magento\Framework\View\Element\Template
     */
    public function getToolbar()
    {
        return $this->getLayout()->getBlock('page.actions.toolbar');
    }
}
