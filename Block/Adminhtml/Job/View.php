<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Job;

use Algolia\AlgoliaSearch\Model\Job;
use Algolia\AlgoliaSearch\Model\JobFactory;
use Algolia\AlgoliaSearch\Model\ResourceModel\Job as JobResource;
use Magento\Backend\Block\Widget\Button;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    protected ?Job $currentJob = null;

    public function __construct(
        Context               $context,
        protected JobFactory  $jobFactory,
        protected JobResource $jobResource,
        array                 $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @inheritdoc
     */
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

    public function getCurrentJob(): Job
    {
        if ($this->currentJob !== null) {
            return $this->currentJob;
        }

        $job = $this->jobFactory->create();
        $this->jobResource->load($job, (int) $this->getRequest()->getParam('id'));

        $this->currentJob = $job;

        return $this->currentJob;
    }

    /**
     * @return string
     */
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
