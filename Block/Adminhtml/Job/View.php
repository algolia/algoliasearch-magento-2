<?php

namespace Algolia\AlgoliaSearch\Block\Adminhtml\Job;

use Magento\Backend\Block\Widget\Button;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;

class View extends Template
{
    /** @var \Magento\Framework\Registry */
    protected $coreRegistry;

    /**
     * @param Context       $context
     * @param Registry      $coreRegistry
     * @param array         $data
     */
    public function __construct(
        Context       $context,
        Registry      $coreRegistry,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->coreRegistry = $coreRegistry;
    }

    /** {@inheritdoc} */
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
        return $this->coreRegistry->registry('current_job');
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
